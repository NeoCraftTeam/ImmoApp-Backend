<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Http\Requests\Api\V1\ClerkExchangeRequest;
use App\Http\Resources\UserResource;
use App\Mail\BailleurWelcomeEmail;
use App\Mail\VerificationCodeMail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\ClerkJwtService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

final class ClerkAuthController
{
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

        $clerkId = (string) ($clerkUser['id'] ?? '');
        $firstName = (string) ($clerkUser['first_name'] ?? 'Utilisateur');
        $lastName = (string) ($clerkUser['last_name'] ?? '');
        $avatar = isset($clerkUser['image_url']) ? (string) $clerkUser['image_url'] : null;

        $email = $this->resolveClerkEmail($clerkUser);

        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->where('email', $email)->first() : null);

        if ($user !== null) {
            if ($user->clerk_id === null || $user->clerk_id !== $clerkId) {
                $user->update(['clerk_id' => $clerkId]);
            }

            $user->tokens()->where('name', 'clerk-exchange')->delete();
            $token = $user->createToken('clerk-exchange', ['*'], now()->addDay());

            auth()->setUser($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($user);
            }

            return response()->json([
                'access_token' => $token->plainTextToken,
                'user' => new UserResource($user),
                'panel_sso_url' => $this->buildPanelSsoUrl($user),
            ]);
        }

        $existingPending = Cache::get('clerk_pending_'.$clerkId, []);
        $requestedIntent = $request->input('registration_intent');
        $registrationIntent = in_array($requestedIntent, ['customer', 'agent'], true)
            ? $requestedIntent
            : ($existingPending['registration_intent'] ?? 'customer');

        Cache::put('clerk_pending_'.$clerkId, array_merge($existingPending, [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'avatar' => $avatar,
            'registration_intent' => $registrationIntent,
        ]), now()->addMinutes(15));

        $otpCooldownKey = 'clerk_otp_sent_'.$clerkId;
        $existingOtp = Cache::get('clerk_otp_'.$clerkId);

        if ($existingOtp !== null && Cache::has($otpCooldownKey)) {
            return response()->json([
                'state' => 'otp_required',
                'email_hint' => $email !== null ? $this->maskEmail($email) : null,
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put('clerk_otp_'.$clerkId, $otp, now()->addMinutes(10));
        Cache::put($otpCooldownKey, true, now()->addSeconds(60));

        if ($email !== null) {
            $requestedFrom = request()->ip() ?? 'inconnu';
            $requestedAt = now()->translatedFormat('d F Y à H:i');

            Mail::to($email, $firstName)
                ->queue(new VerificationCodeMail($otp, $requestedFrom, $requestedAt));
        }

        return response()->json([
            'state' => 'otp_required',
            'email_hint' => $email !== null ? $this->maskEmail($email) : null,
        ]);
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

        $clerkId = (string) ($clerkUser['id'] ?? '');
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
            if ($user->clerk_id === null) {
                $user->update(['clerk_id' => $clerkId]);
            }

            Cache::forget('clerk_verified_'.$clerkId);
            Cache::forget('clerk_pending_'.$clerkId);

            $user->tokens()->where('name', 'clerk-exchange')->delete();
            $token = $user->createToken('clerk-exchange', ['*'], now()->addDay());
            auth()->setUser($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($user);
            }

            return response()->json([
                'state' => 'authenticated',
                'access_token' => $token->plainTextToken,
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

        $clerkId = (string) ($clerkUser['id'] ?? '');

        /** @var array{verified?: bool} $pendingCheck */
        $pendingCheck = Cache::get('clerk_pending_'.$clerkId, []);

        if (!Cache::get('clerk_verified_'.$clerkId) && empty($pendingCheck['verified'])) {
            return response()->json(['message' => 'Vérification email requise.'], 403);
        }

        if (!$request->filled('phone_number')) {
            return response()->json(['message' => 'Le numéro de téléphone est obligatoire.'], 422);
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

            try {
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
                ]);
                $user->save();
            } catch (UniqueConstraintViolationException) {
                $user = User::query()->where('clerk_id', $clerkId)->first()
                    ?? ($email !== null ? User::query()->where('email', $email)->first() : null);

                if ($user === null) {
                    return response()->json(['message' => 'Erreur lors de la création du compte.'], 409);
                }
            }

            $isNew = true;

            if ($email !== null && !str_ends_with((string) $email, '@clerk.local')) {
                if ($role === UserRole::AGENT) {
                    Mail::to($email, $firstName)->queue(new BailleurWelcomeEmail($user));
                } else {
                    Mail::to($email, $firstName)->queue(new WelcomeEmail($user));
                }
            }
        } else {
            if ($user->clerk_id === null) {
                $user->update(['clerk_id' => $clerkId]);
            }
        }

        Cache::forget('clerk_verified_'.$clerkId);
        Cache::forget('clerk_pending_'.$clerkId);

        $user->tokens()->where('name', 'clerk-exchange')->delete();
        $token = $user->createToken('clerk-exchange', ['*'], now()->addDay());
        auth()->setUser($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
            Auth::guard('web')->login($user);
        }

        return response()->json([
            'access_token' => $token->plainTextToken,
            'user' => new UserResource($user),
            'panel_sso_url' => $this->buildPanelSsoUrl($user),
        ], $isNew ? 201 : 200);
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

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 1));

        return $masked.'@'.$domain;
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
}
