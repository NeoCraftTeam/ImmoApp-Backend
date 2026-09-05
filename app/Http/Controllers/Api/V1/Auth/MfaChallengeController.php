<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTOs\MfaChallenge;
use App\Enums\UserRole;
use App\Exceptions\RoleContextMismatchException;
use App\Http\Requests\Api\V1\Auth\MfaChallengeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\MfaChallengeService;
use App\Services\Auth\MfaService;
use App\Services\Auth\TokenService;
use App\Support\AuthError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\NewAccessToken;
use OpenApi\Attributes as OA;

/**
 * Second step of a login that requires two factors.
 *
 * Public on purpose — the caller has no Sanctum token yet; the `mfa_token`
 * minted by the first step *is* the credential. Nothing here can be reached
 * without having already passed a password, an OAuth identity or a Clerk JWT.
 *
 * A passkey login never lands here: WebAuthn is itself a second factor
 * (possession of the authenticator + user verification), so
 * `LoginService::issueApiTokenForLoginContext()` stays a one-step flow.
 */
final readonly class MfaChallengeController
{
    public function __construct(
        private MfaService $mfa,
        private MfaChallengeService $challenges,
        private LoginService $loginService,
        private TokenService $tokenService,
    ) {}

    #[OA\Post(
        path: '/api/v1/auth/mfa/challenge',
        summary: 'Compléter une connexion en attente de second facteur',
        description: 'Échange le `mfa_token` renvoyé par la connexion (403 `MFA_CHALLENGE_REQUIRED`) '
            .'contre un token Sanctum, après vérification du code TOTP, du code de secours ou du code email. '
            .'Appelé avec `method=email` et sans `code`, envoie un code par email et répond 202.',
        tags: ['🔐 Authentification'],
    )]
    #[OA\Response(response: 200, description: 'Connexion complétée, token émis')]
    #[OA\Response(response: 202, description: 'Code envoyé par email')]
    #[OA\Response(response: 403, description: 'Accès au panneau refusé pour ce rôle')]
    #[OA\Response(response: 422, description: 'Session de vérification invalide ou code incorrect')]
    #[OA\Response(response: 429, description: 'Trop de tentatives')]
    public function __invoke(MfaChallengeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $challenge = $this->challenges->retrieve((string) $validated['mfa_token']);

        if ($challenge === null) {
            return response()->json([
                'message' => 'Session de vérification expirée. Reconnectez-vous.',
                'code' => MfaChallengeService::CODE_INVALID,
            ], 422);
        }

        if (RateLimiter::tooManyAttempts($challenge->throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($challenge->throttleKey);

            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
                'code' => 'RATE_LIMITED',
                'retry_after' => $seconds,
            ], 429);
        }

        $method = isset($validated['method']) ? (string) $validated['method'] : null;
        $code = trim((string) ($validated['code'] ?? ''));

        if ($method === MfaService::METHOD_EMAIL && $code === '') {
            return $this->sendEmailCode($challenge, $request);
        }

        if ($code === '') {
            return response()->json([
                'message' => 'Le code de vérification est obligatoire.',
                'code' => MfaChallengeService::CODE_BAD_CODE,
                'attempts_remaining' => max(0, $this->challenges->maxAttempts() - $challenge->attempts),
            ], 422);
        }

        $usedMethod = $this->verifyCode($challenge, $method, $code);

        if ($usedMethod === null) {
            return $this->rejectCode($challenge);
        }

        $this->challenges->consume($challenge);
        RateLimiter::clear($challenge->throttleKey);

        return $this->completeLogin($challenge, $request, $usedMethod);
    }

    /**
     * Mail a one-time code to accounts whose second factor is email.
     */
    private function sendEmailCode(MfaChallenge $challenge, Request $request): JsonResponse
    {
        if (!$this->mfa->hasEmail($challenge->user)) {
            return response()->json([
                'message' => 'La vérification par email n\'est pas activée sur ce compte.',
                'code' => 'MFA_EMAIL_NOT_ENABLED',
            ], 422);
        }

        $cooldown = $this->challenges->emailOtpCooldownRemaining($challenge);

        if ($cooldown > 0) {
            return response()->json([
                'message' => 'Un code a déjà été envoyé. Patientez '.$cooldown.' secondes.',
                'code_sent' => true,
                'retry_after' => $cooldown,
            ], 429);
        }

        $this->challenges->sendEmailOtp($challenge, $request->ip());

        return response()->json([
            'message' => 'Code envoyé à '.$this->mfa->maskEmail((string) $challenge->user->email).'.',
            'code_sent' => true,
            'masked_email' => $this->mfa->maskEmail((string) $challenge->user->email),
        ], 202);
    }

    /**
     * Which factor the submitted code satisfies, or null when none does.
     *
     * `method` is only a hint from the client: with no hint we try TOTP, then a
     * recovery code, then the emailed OTP, so an older client that only knows
     * how to post `{mfa_token, code}` still works with every enrolment.
     */
    private function verifyCode(MfaChallenge $challenge, ?string $method, string $code): ?string
    {
        if ($method === MfaService::METHOD_EMAIL) {
            return $this->challenges->verifyEmailOtp($challenge, $code)
                ? MfaService::METHOD_EMAIL
                : null;
        }

        if ($method === MfaService::METHOD_RECOVERY) {
            return $this->mfa->verifyRecoveryCode($challenge->user, $code)
                ? MfaService::METHOD_RECOVERY
                : null;
        }

        $used = $this->mfa->verifyTotpOrRecoveryCode($challenge->user, $code);

        if ($used !== null) {
            return $used;
        }

        if ($this->mfa->hasEmail($challenge->user) && $this->challenges->verifyEmailOtp($challenge, $code)) {
            return MfaService::METHOD_EMAIL;
        }

        return null;
    }

    private function rejectCode(MfaChallenge $challenge): JsonResponse
    {
        RateLimiter::hit($challenge->throttleKey, 300);
        $remaining = $this->challenges->registerFailure($challenge);

        Log::warning('MFA challenge failed', [
            'user_id' => $challenge->user->getKey(),
            'source' => $challenge->source,
            'attempts_remaining' => $remaining,
        ]);

        if ($remaining === 0) {
            return response()->json([
                'message' => 'Trop de codes incorrects. Reconnectez-vous pour recommencer.',
                'code' => MfaChallengeService::CODE_EXHAUSTED,
                'attempts_remaining' => 0,
            ], 422);
        }

        return response()->json([
            'message' => 'Code invalide ou expiré.',
            'code' => MfaChallengeService::CODE_BAD_CODE,
            'attempts_remaining' => $remaining,
        ], 422);
    }

    /**
     * Issue the Sanctum token the first step withheld.
     *
     * Each source reproduces exactly what its own controller would have done, so
     * the second factor changes *when* the token is issued, never *how*.
     */
    private function completeLogin(MfaChallenge $challenge, Request $request, string $usedMethod): JsonResponse
    {
        $user = $challenge->user;

        try {
            $token = match ($challenge->source) {
                MfaChallengeService::SOURCE_PASSWORD => $this->loginService
                    ->completeLogin($user, $request, $challenge->loginContext ?? 'client')
                    ->token,
                MfaChallengeService::SOURCE_CLERK => $this->issueClerkToken($user, $challenge->loginContext),
                MfaChallengeService::SOURCE_OAUTH => $this->issueOAuthToken($user, $request, 'oauth_'.($challenge->provider ?? 'unknown')),
                MfaChallengeService::SOURCE_OAUTH_LINK => $this->issueOAuthToken($user, $request, 'oauth_link'),
                default => null,
            };
        } catch (RoleContextMismatchException $e) {
            return AuthError::loginPanelMismatch(code: $e->authCode);
        }

        if ($token === null) {
            return response()->json([
                'message' => 'Session de vérification expirée. Reconnectez-vous.',
                'code' => MfaChallengeService::CODE_INVALID,
            ], 422);
        }

        // The user just proved a second factor: do not let RequireApiMfa ask an
        // admin for another one on the very next request.
        $this->mfa->markTokenVerified((string) $token->accessToken->getKey());

        auth()->setUser($user);

        if ($challenge->stateful && $request->hasSession()) {
            $request->session()->regenerate();
            Auth::guard('web')->login($user);
        }

        Log::info('MFA challenge passed', [
            'user_id' => $user->getKey(),
            'source' => $challenge->source,
            'method' => $usedMethod,
        ]);

        return response()->json([
            'message' => 'Connexion réussie.',
            'access_token' => $token->plainTextToken,
            // `token` alias: the OAuth and mobile clients read this key.
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'role' => $user->role->value,
            'type' => $user->type?->value,
            'user' => new UserResource($user),
            'is_new_user' => $challenge->isNewUser,
            'mfa_method' => $usedMethod,
            'recovery_codes_remaining' => $this->mfa->remainingRecoveryCodeCount($user),
            'panel_sso_url' => $this->buildPanelSsoUrl($user),
        ]);
    }

    /**
     * @throws RoleContextMismatchException
     */
    private function issueClerkToken(User $user, ?string $loginContext): NewAccessToken
    {
        if ($loginContext !== null) {
            $this->loginService->assertRoleContext($user, $loginContext);
        }

        $prefix = $user->sanctumSessionPrefix();

        return $this->tokenService->rotateForUser($user, 'clerk', "{$prefix}_clerk_%", $prefix);
    }

    /**
     * OAuth tokens are created, not rotated — mirroring SocialAuthController,
     * including the `last_login_*` stamp it applies just before issuing them.
     */
    private function issueOAuthToken(User $user, Request $request, string $suffix): NewAccessToken
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return $this->tokenService->createForUser($user, $suffix);
    }

    private function buildPanelSsoUrl(User $user): ?string
    {
        if ($user->role === UserRole::CUSTOMER) {
            return null;
        }

        return URL::temporarySignedRoute(
            'panel.sso',
            now()->addSeconds(60),
            ['user_id' => $user->id],
        );
    }
}
