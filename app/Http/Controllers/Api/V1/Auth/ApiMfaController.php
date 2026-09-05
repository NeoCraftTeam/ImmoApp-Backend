<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\VerifyMfaRequest;
use App\Mail\VerificationCodeMail;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * API Multi-Factor Authentication controller.
 *
 * Allows any authenticated user to verify their configured MFA method
 * (TOTP app or email OTP) once per session. Admin users are forced to verify
 * by `RequireApiMfa` on admin routes; non-admin users may opt-in via the
 * `step.up.mfa` middleware on sensitive routes (password change, account
 * deletion, etc.) or by calling `/auth/mfa/verify` proactively.
 *
 * This is *step-up* verification of an already-authenticated token — not the
 * login second factor. A login that owes a second factor never reaches here:
 * it gets a 403 `MFA_CHALLENGE_REQUIRED` and completes at
 * {@see MfaChallengeController} with no token at all.
 *
 * Flow:
 *  1. User logs in via POST /auth/login → receives Sanctum token
 *  2. User accesses protected route → 403 MFA_REQUIRED (if applicable)
 *  3. User calls GET /auth/mfa/status to discover configured methods
 *  4. User calls POST /auth/mfa/verify with TOTP code, recovery code or email OTP
 *  5. Token is marked MFA-verified in cache for MFA_API_SESSION_LIFETIME minutes
 *  6. User retries protected route → granted
 */
final readonly class ApiMfaController
{
    public function __construct(private MfaService $mfa) {}

    /**
     * How long (minutes) the MFA verification is valid for a given token.
     * Defaults to 8 hours. Override via MFA_API_SESSION_LIFETIME env var.
     */
    private function sessionLifetime(): int
    {
        return $this->mfa->apiSessionLifetime();
    }

    /**
     * Return the MFA status for the current authenticated user/token.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $token = $user->currentAccessToken();
        $cacheKey = $token instanceof PersonalAccessToken
            ? 'api_mfa_verified_'.$token->getKey()
            : null;

        $hasMfaConfigured = $this->mfa->isEnabled($user);

        return response()->json([
            // Admins are forced to verify on admin routes (RequireApiMfa).
            // Non-admin users may still opt-in via `step.up.mfa` on sensitive routes.
            'mfa_required' => $user->isAdmin() && $hasMfaConfigured,
            'mfa_configured' => $hasMfaConfigured,
            'mfa_verified' => $cacheKey !== null && Cache::has($cacheKey),
            'methods' => $this->mfa->enabledMethods($user),
            'recovery_codes_remaining' => $this->mfa->remainingRecoveryCodeCount($user),
        ]);
    }

    /**
     * Verify MFA code and mark the current token as MFA-verified.
     *
     * Accepts:
     *  - method=totp + code: TOTP code from authenticator app (a single-use
     *    recovery code is also accepted, for a lost/reset authenticator)
     *  - method=email: triggers email OTP and waits for code on second call
     */
    public function verify(VerifyMfaRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        // MFA verification is available to any user who has a method configured.
        // Admins are forced into this flow by RequireApiMfa; non-admins opt in by
        // setting up TOTP/email via /auth/mfa/setup or by hitting a `step.up.mfa`
        // gated route.
        if (!$this->mfa->isEnabled($user)) {
            return response()->json([
                'message' => 'Aucune méthode MFA configurée pour ce compte.',
                'code' => 'MFA_NOT_CONFIGURED',
            ], 422);
        }

        $request->validated();

        $method = $request->input('method');
        $token = $user->currentAccessToken();

        if (!$token instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Token invalide.'], 401);
        }

        // Rate-limit MFA attempts per token
        $rateLimitKey = 'mfa-verify:'.$token->getKey();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
                'retry_after' => $seconds,
            ], 429);
        }

        if ($method === 'totp') {
            return $this->verifyTotp($request, $user, $token, $rateLimitKey);
        }

        return $this->verifyEmailOtp($request, $user, $token, $rateLimitKey);
    }

    /**
     * Verify a TOTP code from the authenticator app, or a single-use recovery
     * code for a user who lost it.
     *
     * Delegates to {@see MfaService} so the accepted window (±4 min) and the
     * replay ledger are the same here, on the login challenge and in the
     * Filament panel — a code accepted anywhere is spent everywhere.
     */
    private function verifyTotp(Request $request, User $user, PersonalAccessToken $token, string $rateLimitKey): JsonResponse
    {
        if (!$this->mfa->hasTotp($user)) {
            return response()->json(['message' => 'Authentification TOTP non configurée.'], 422);
        }

        $code = trim((string) $request->input('code', ''));

        if ($code === '') {
            return response()->json(['message' => 'Le code TOTP est obligatoire.'], 422);
        }

        $usedMethod = $this->mfa->verifyTotpOrRecoveryCode($user, $code);

        if ($usedMethod === null) {
            RateLimiter::hit($rateLimitKey, 300);

            return response()->json(['message' => 'Code TOTP invalide ou expiré.'], 422);
        }

        RateLimiter::clear($rateLimitKey);
        $this->markVerified($token);

        return response()->json([
            'message' => 'MFA vérifié avec succès.',
            'mfa_verified' => true,
            'mfa_method' => $usedMethod,
            'recovery_codes_remaining' => $this->mfa->remainingRecoveryCodeCount($user),
            'expires_in_minutes' => $this->sessionLifetime(),
        ]);
    }

    /**
     * Verify email OTP code.
     *
     * First call (no code provided): sends OTP email and returns 202.
     * Second call (with code):       verifies the OTP and marks token as verified.
     */
    private function verifyEmailOtp(Request $request, User $user, PersonalAccessToken $token, string $rateLimitKey): JsonResponse
    {
        if (!$this->mfa->hasEmail($user)) {
            return response()->json(['message' => 'Authentification par email non configurée.'], 422);
        }

        $code = trim((string) $request->input('code', ''));
        $otpCacheKey = 'api_mfa_email_otp_'.$token->getKey();
        $cooldownKey = 'api_mfa_email_cooldown_'.$token->getKey();

        // No code provided → send email OTP
        if ($code === '') {
            if (Cache::has($cooldownKey)) {
                $seconds = (int) Cache::get($cooldownKey.'_ttl', 60);

                return response()->json([
                    'message' => 'Un code a déjà été envoyé. Patientez '.$seconds.' secondes.',
                    'code_sent' => true,
                ], 429);
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put($otpCacheKey, $otp, now()->addMinutes(10));
            Cache::put($cooldownKey, true, now()->addSeconds(60));

            Mail::to($user->email, $user->firstname)
                ->queue(new VerificationCodeMail(
                    $otp,
                    $request->ip() ?? 'inconnu',
                    now()->translatedFormat('d F Y à H:i'),
                ));

            return response()->json([
                'message' => 'Code envoyé à '.$this->mfa->maskEmail((string) $user->email).'. Saisissez-le dans ce formulaire.',
                'code_sent' => true,
                'masked_email' => $this->mfa->maskEmail((string) $user->email),
            ], 202);
        }

        // Code provided → verify
        $cachedOtp = Cache::get($otpCacheKey);

        if (!is_string($cachedOtp) || !hash_equals($cachedOtp, $code)) {
            RateLimiter::hit($rateLimitKey, 300);

            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        Cache::forget($otpCacheKey);
        Cache::forget($cooldownKey);
        RateLimiter::clear($rateLimitKey);
        $this->markVerified($token);

        return response()->json([
            'message' => 'MFA vérifié avec succès.',
            'mfa_verified' => true,
            'mfa_method' => MfaService::METHOD_EMAIL,
            'expires_in_minutes' => $this->sessionLifetime(),
        ]);
    }

    private function markVerified(PersonalAccessToken $token): void
    {
        $this->mfa->markTokenVerified((string) $token->getKey());
    }
}
