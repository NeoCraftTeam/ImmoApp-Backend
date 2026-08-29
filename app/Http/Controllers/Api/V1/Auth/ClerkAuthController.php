<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Exceptions\RoleContextMismatchException;
use App\Http\Requests\Api\V1\ClerkExchangeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\ClerkJwtService;
use App\Services\Auth\LoginService;
use App\Services\Auth\TokenService;
use App\Services\User\UserWelcomeService;
use App\Services\UtmAttributionService;
use App\Support\AuthError;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\NewAccessToken;

final readonly class ClerkAuthController
{
    public function __construct(
        private TokenService $tokenService,
        private LoginService $loginService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/auth/clerk/exchange",
     *     summary="Échanger un token Clerk contre un token Sanctum",
     *     tags={"🔐 Authentification"},
     *
     *     @OA\Response(response=200, description="Authentification réussie ou OTP requis"),
     *     @OA\Response(response=401, description="Token Clerk invalide")
     * )
     */
    public function clerkExchange(ClerkExchangeRequest $request, ClerkJwtService $clerk): JsonResponse
    {
        /** @var string $bearerToken */
        $bearerToken = $request->bearerToken();

        $clerkUser = $clerk->verifyAndFetchUser($bearerToken);

        if ($clerkUser === null) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        if (!isset($clerkUser['id']) || empty($clerkUser['email_addresses'])) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        $clerkId = (string) $clerkUser['id'];
        $firstName = (string) ($clerkUser['first_name'] ?? 'Utilisateur');
        $lastName = (string) ($clerkUser['last_name'] ?? '');
        $avatar = isset($clerkUser['image_url']) ? (string) $clerkUser['image_url'] : null;

        $email = $this->resolveClerkEmail($clerkUser);

        // SEC: Look up by clerk_id first. On miss, fall back to email so that cross-provider
        // logins work (e.g. Facebook → Google with the same verified email). Clerk verifies
        // email ownership before issuing JWTs, so matching a verified account by email is safe.
        // We still restrict the email fallback to accounts with a verified email address to
        // prevent an unverified-email account from being taken over via a Clerk identity.
        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->where('email', $email)->whereNotNull('email_verified_at')->first() : null);

        if ($user !== null) {
            // SEC: Block Clerk login for accounts whose email is still unverified
            // (e.g. registered via email/password but OTP never completed).
            // Our OTP gate must not be bypassable via a Clerk identity with the same email.
            if ($user->email_verified_at === null) {
                return response()->json([
                    'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
                    'email_verification_required' => true,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ], 403);
            }

            if ($user->clerk_id === null || $user->clerk_id !== $clerkId) {
                $user->update(['clerk_id' => $clerkId]);
            }

            // Refresh the profile captured from the OAuth provider on every
            // login: avatar re-synced, first/last name healed only when still
            // empty/placeholder (see User::syncOAuthProfile()).
            $user->syncOAuthProfile($firstName, $lastName, $avatar);

            try {
                $token = $this->rotateClerkToken($user, $request->input('login_context'));
            } catch (RoleContextMismatchException $e) {
                return AuthError::loginPanelMismatch(code: $e->authCode);
            }

            auth()->setUser($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($user);
            }

            return response()->json([
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'user' => new UserResource($user),
                'panel_sso_url' => $this->buildPanelSsoUrl($user),
            ]);
        }

        $existingPending = Cache::get('clerk_pending_'.$clerkId, []);
        $requestedIntent = $request->input('registration_intent');
        $registrationIntent = in_array($requestedIntent, ['customer', 'agent'], true)
            ? $requestedIntent
            : ($existingPending['registration_intent'] ?? 'customer');

        $utmFromRequest = array_intersect_key(
            $request->validated(),
            array_flip(UtmAttributionService::ATTRIBUTION_REQUEST_KEYS),
        );

        Cache::put('clerk_pending_'.$clerkId, array_merge($existingPending, [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'avatar' => $avatar,
            'registration_intent' => $registrationIntent,
        ], $utmFromRequest), now()->addMinutes(15));

        // OAuth via Clerk : l'email est déjà vérifié par le provider — pas d'OTP Laravel
        // (réservé à l'inscription e-mail + mot de passe).
        if (!$this->isClerkIdentityVerified($clerkUser)) {
            return response()->json([
                'message' => 'Votre adresse email Clerk n\'est pas encore vérifiée.',
            ], 403);
        }

        $user = $this->findOrCreateClerkOAuthUser(
            $request,
            $clerkId,
            $email,
            $firstName,
            $lastName,
            $avatar,
            $registrationIntent,
            array_merge($existingPending, $utmFromRequest),
        );

        Cache::forget('clerk_pending_'.$clerkId);
        Cache::forget('clerk_otp_'.$clerkId);
        Cache::forget('clerk_verified_'.$clerkId);
        Cache::forget('clerk_otp_sent_'.$clerkId);

        try {
            $token = $this->rotateClerkToken($user, $request->input('login_context'));
        } catch (RoleContextMismatchException $e) {
            return AuthError::loginPanelMismatch(code: $e->authCode);
        }

        auth()->setUser($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
            Auth::guard('web')->login($user);
        }

        return response()->json([
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'user' => new UserResource($user),
            'panel_sso_url' => $this->buildPanelSsoUrl($user),
        ], $user->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/clerk/verify-otp",
     *     summary="Vérifier le code OTP Clerk",
     *     tags={"🔐 Authentification"},
     *
     *     @OA\Response(response=200, description="OTP validé"),
     *     @OA\Response(response=401, description="Token Clerk invalide"),
     *     @OA\Response(response=422, description="Code OTP invalide ou expiré")
     * )
     */
    public function verifyClerkOtp(Request $request, ClerkJwtService $clerk): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return response()->json(['message' => 'Token non fourni.'], 401);
        }

        $clerkUser = $clerk->verifyAndFetchUser($bearerToken);

        if ($clerkUser === null) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        if (!isset($clerkUser['id']) || empty($clerkUser['email_addresses'])) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        $clerkId = (string) $clerkUser['id'];
        $otp = (string) ($request->input('otp', ''));

        $rateLimitKey = 'otp-verify:'.$clerkId;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            Cache::forget('clerk_otp_'.$clerkId);

            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
                'retry_after' => $seconds,
            ], 429);
        }

        $cachedOtp = Cache::get('clerk_otp_'.$clerkId);

        if ($cachedOtp === null || !hash_equals($cachedOtp, $otp)) {
            RateLimiter::hit($rateLimitKey, 300);

            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        RateLimiter::clear($rateLimitKey);
        Cache::forget('clerk_otp_'.$clerkId);
        Cache::put('clerk_verified_'.$clerkId, true, now()->addMinutes(15));

        $email = $this->resolveClerkEmail($clerkUser);

        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->whereNull('clerk_id')->where('email', $email)->first() : null);

        if ($user !== null) {
            // SEC: Same gate as clerkExchange — OTP proof does not override our email-verification
            // requirement for accounts that registered but never completed OTP.
            if ($user->email_verified_at === null) {
                Cache::forget('clerk_verified_'.$clerkId);
                Cache::forget('clerk_pending_'.$clerkId);
                Cache::forget('clerk_otp_'.$clerkId);

                return response()->json([
                    'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
                    'email_verification_required' => true,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ], 403);
            }

            if ($user->clerk_id === null) {
                $user->update(['clerk_id' => $clerkId]);
            }

            Cache::forget('clerk_verified_'.$clerkId);
            Cache::forget('clerk_pending_'.$clerkId);

            try {
                $token = $this->rotateClerkToken($user, $request->input('login_context'));
            } catch (RoleContextMismatchException $e) {
                return AuthError::loginPanelMismatch(code: $e->authCode);
            }

            auth()->setUser($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($user);
            }

            return response()->json([
                'state' => 'authenticated',
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
                'user' => new UserResource($user),
                'panel_sso_url' => $this->buildPanelSsoUrl($user),
            ]);
        }

        /** @var array{firstname: string, lastname: string, email: string|null, avatar: string|null, verified?: bool} $pending */
        $pending = Cache::get('clerk_pending_'.$clerkId, []);

        $pending['verified'] = true;
        Cache::put('clerk_pending_'.$clerkId, $pending, now()->addMinutes(15));

        return response()->json([
            'state' => 'profile_required',
            'prefill' => $pending,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/clerk/complete-profile",
     *     summary="Compléter le profil après vérification OTP",
     *     tags={"🔐 Authentification"},
     *
     *     @OA\Response(response=201, description="Compte créé avec succès"),
     *     @OA\Response(response=401, description="Token Clerk invalide"),
     *     @OA\Response(response=403, description="Vérification email requise")
     * )
     */
    public function completeClerkProfile(ClerkExchangeRequest $request, ClerkJwtService $clerk): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        $clerkUser = $clerk->verifyAndFetchUser($bearerToken);

        if ($clerkUser === null) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        if (!isset($clerkUser['id']) || empty($clerkUser['email_addresses'])) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        $clerkId = (string) $clerkUser['id'];

        /** @var array{verified?: bool} $pendingCheck */
        $pendingCheck = Cache::get('clerk_pending_'.$clerkId, []);

        if (!Cache::get('clerk_verified_'.$clerkId) && empty($pendingCheck['verified'])) {
            return response()->json(['message' => 'Vérification email requise.'], 403);
        }

        // phone_number is optional — users may skip profile completion via OAuth flow
        if ($request->filled('phone_number')) {
            $phone = (string) $request->input('phone_number');
            if (!preg_match('/^[\d\s\-\+\(\)]{8,20}$/', $phone)) {
                return response()->json(['message' => 'Numéro de téléphone invalide.'], 422);
            }
        }

        /** @var array{firstname?: string, lastname?: string, email?: string|null, avatar?: string|null, registration_intent?: string} $pending */
        $pending = Cache::get('clerk_pending_'.$clerkId, []);
        $firstName = (string) ($pending['firstname'] ?? $clerkUser['first_name'] ?? 'Utilisateur');
        $lastName = (string) ($pending['lastname'] ?? $clerkUser['last_name'] ?? '');
        $avatar = $pending['avatar'] ?? (isset($clerkUser['image_url']) ? (string) $clerkUser['image_url'] : null);
        $email = $pending['email'] ?? $this->resolveClerkEmail($clerkUser);

        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->whereNull('clerk_id')->where('email', $email)->first() : null);

        $isNew = false;

        if ($user === null) {
            $registrationIntent = ($pending['registration_intent'] ?? 'customer') === 'agent' ? 'agent' : 'customer';
            $role = $registrationIntent === 'agent' ? UserRole::AGENT : UserRole::CUSTOMER;
            $type = UserType::INDIVIDUAL;

            $utmPayload = array_merge(
                array_intersect_key($pending, array_flip(UtmAttributionService::ATTRIBUTION_REQUEST_KEYS)),
                array_intersect_key(
                    $request->validated(),
                    array_flip(UtmAttributionService::ATTRIBUTION_REQUEST_KEYS),
                ),
            );

            try {
                $utm = app(UtmAttributionService::class);

                $user = new User;
                $user->fill([
                    'clerk_id' => $clerkId,
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'email' => $email ?? $clerkId.'@clerk.local',
                    'phone_number' => $request->input('phone_number'),
                    'city_id' => $request->input('city_id'),
                    'avatar' => $avatar,
                ]);
                $user->forceFill([
                    'role' => $role,
                    'type' => $type,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'registration_ip' => $request->ip(),
                    'last_login_ip' => $request->ip(),
                ]);
                $user->forceFill($utm->attributesForNewUser($request, $utmPayload));
                $user->save();
                $utm->linkSessionVisitsToUser($user, isset($utmPayload['session_id']) && is_string($utmPayload['session_id']) ? $utmPayload['session_id'] : null);
            } catch (UniqueConstraintViolationException) {
                $user = User::query()->where('clerk_id', $clerkId)->first()
                    ?? ($email !== null ? User::query()->where('email', $email)->first() : null);

                if ($user === null) {
                    return response()->json(['message' => 'Erreur lors de la création du compte.'], 409);
                }
            }

            $isNew = true;

            try {
                app(UserWelcomeService::class)->handle($user);
            } catch (\Throwable $e) {
                Log::error('UserWelcomeService failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            if ($user->clerk_id === null) {
                $user->update(['clerk_id' => $clerkId]);
            }
        }

        Cache::forget('clerk_verified_'.$clerkId);
        Cache::forget('clerk_pending_'.$clerkId);

        try {
            $token = $this->rotateClerkToken($user, $request->input('login_context'));
        } catch (RoleContextMismatchException $e) {
            return AuthError::loginPanelMismatch(code: $e->authCode);
        }

        auth()->setUser($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
            Auth::guard('web')->login($user);
        }

        return response()->json([
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'user' => new UserResource($user),
            'panel_sso_url' => $this->buildPanelSsoUrl($user),
        ], $isNew ? 201 : 200);
    }

    /**
     * @throws RoleContextMismatchException
     */
    private function rotateClerkToken(User $user, ?string $loginContext): NewAccessToken
    {
        if ($loginContext !== null) {
            $this->loginService->assertRoleContext($user, $loginContext);
        }

        $prefix = $user->sanctumSessionPrefix();

        return $this->tokenService->rotateForUser($user, 'clerk', "{$prefix}_clerk_%", $prefix);
    }

    /**
     * Resolve the primary email from a Clerk user payload.
     *
     * @param  array<string, mixed>  $clerkUser
     */
    private function resolveClerkEmail(array $clerkUser): ?string
    {
        $emailAddresses = $clerkUser['email_addresses'] ?? [];
        $primaryEmailId = $clerkUser['primary_email_address_id'] ?? null;

        foreach ($emailAddresses as $addr) {
            if ($primaryEmailId !== null && ($addr['id'] ?? null) === $primaryEmailId) {
                return $addr['email_address'];
            }
        }

        return count($emailAddresses) > 0 ? ($emailAddresses[0]['email_address'] ?? null) : null;
    }

    private function buildPanelSsoUrl(User $user): ?string
    {
        if ($user->role === UserRole::CUSTOMER) {
            return null;
        }

        return URL::temporarySignedRoute(
            'panel.sso',
            now()->addSeconds(60),
            ['user_id' => $user->id]
        );
    }

    /**
     * Clerk OAuth identities are trusted once the provider (Google/Facebook/GitHub) verified the email.
     *
     * @param  array<string, mixed>  $clerkUser
     */
    private function isClerkIdentityVerified(array $clerkUser): bool
    {
        if ($this->hasOAuthExternalAccount($clerkUser)) {
            return true;
        }

        $email = $this->resolveClerkEmail($clerkUser);

        if ($email === null) {
            return false;
        }

        foreach ($clerkUser['email_addresses'] ?? [] as $addr) {
            if (!is_array($addr)) {
                continue;
            }

            if (($addr['email_address'] ?? null) !== $email) {
                continue;
            }

            return ($addr['verification']['status'] ?? null) === 'verified';
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $clerkUser
     */
    private function hasOAuthExternalAccount(array $clerkUser): bool
    {
        foreach ($clerkUser['external_accounts'] ?? [] as $account) {
            if (!is_array($account)) {
                continue;
            }

            $provider = (string) ($account['provider'] ?? '');

            if (str_starts_with($provider, 'oauth_') || in_array($provider, ['google', 'facebook', 'github', 'apple'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $attributionPayload
     */
    private function findOrCreateClerkOAuthUser(
        ClerkExchangeRequest $request,
        string $clerkId,
        ?string $email,
        string $firstName,
        string $lastName,
        ?string $avatar,
        string $registrationIntent,
        array $attributionPayload,
    ): User {
        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->whereNull('clerk_id')->where('email', $email)->first() : null);

        if ($user !== null) {
            if ($user->clerk_id === null) {
                $user->update(['clerk_id' => $clerkId]);
            }

            return $user;
        }

        $role = $registrationIntent === 'agent' ? UserRole::AGENT : UserRole::CUSTOMER;
        $utm = app(UtmAttributionService::class);

        try {
            $user = new User;
            $user->fill([
                'clerk_id' => $clerkId,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email ?? $clerkId.'@clerk.local',
                'avatar' => $avatar,
            ]);
            $user->forceFill([
                'role' => $role,
                'type' => UserType::INDIVIDUAL,
                'is_active' => true,
                'email_verified_at' => now(),
                'registration_ip' => $request->ip(),
                'last_login_ip' => $request->ip(),
            ]);
            $user->forceFill($utm->attributesForNewUser($request, array_merge(
                $attributionPayload,
                array_intersect_key(
                    $request->validated(),
                    array_flip(UtmAttributionService::ATTRIBUTION_REQUEST_KEYS),
                ),
            )));
            $user->save();

            $sessionId = $attributionPayload['session_id'] ?? null;
            $utm->linkSessionVisitsToUser(
                $user,
                is_string($sessionId) ? $sessionId : null,
            );
        } catch (UniqueConstraintViolationException) {
            $user = User::query()->where('clerk_id', $clerkId)->first()
                ?? ($email !== null ? User::query()->where('email', $email)->first() : null);

            if ($user === null) {
                throw new \RuntimeException('Erreur lors de la création du compte Clerk.');
            }
        }

        try {
            app(UserWelcomeService::class)->handle($user);
        } catch (\Throwable $e) {
            Log::error('UserWelcomeService failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }
}
