<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * API Multi-Factor Authentication controller.
 *
 * Allows admin users to satisfy the RequireApiMfa middleware by verifying
 * their configured MFA method (TOTP app or email OTP) once per session.
 *
 * Flow:
 *  1. Admin logs in via POST /auth/login → receives Sanctum token
 *  2. Admin accesses protected admin route → 403 MFA_REQUIRED
 *  3. Admin calls GET /auth/mfa/status to discover available methods
 *  4. Admin calls POST /auth/mfa/verify with TOTP code or email OTP
 *  5. Token is marked MFA-verified in cache for MFA_API_SESSION_LIFETIME minutes
 *  6. Admin retries protected route → granted
 */
final class ApiMfaController
{
    /**
     * How long (minutes) the MFA verification is valid for a given token.
     * Defaults to 8 hours. Override via MFA_API_SESSION_LIFETIME env var.
     */
    private function sessionLifetime(): int
    {
        return (int) config('auth.mfa_api_session_lifetime', 480);
    }

    /**
     * Return the MFA status for the current authenticated user/token.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $token = $user->currentAccessToken();
        $cacheKey = $token instanceof PersonalAccessToken
            ? 'api_mfa_verified_'.$token->getKey()
            : null;

        $hasTotpConfigured = $user->getAppAuthenticationSecret() !== null;
        $hasEmailConfigured = $user->hasEmailAuthentication();
        $hasMfaConfigured = $hasTotpConfigured || $hasEmailConfigured;

        return response()->json([
            'mfa_required' => $user->isAdmin() && $hasMfaConfigured,
            'mfa_verified' => $cacheKey !== null && Cache::has($cacheKey),
            'methods' => array_filter([
                $hasTotpConfigured ? 'totp' : null,
                $hasEmailConfigured ? 'email' : null,
            ]),
        ]);
    }

    /**
     * Verify MFA code and mark the current token as MFA-verified.
     *
     * Accepts:
     *  - method=totp + code: TOTP code from authenticator app
     *  - method=email: triggers email OTP and waits for code on second call
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Réservé aux administrateurs.'], 403);
        }

        $request->validate([
            'method' => ['required', 'string', 'in:totp,email'],
            'code' => ['nullable', 'string'],
        ]);

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
     * Verify TOTP code from authenticator app.
     */
    private function verifyTotp(Request $request, mixed $user, PersonalAccessToken $token, string $rateLimitKey): JsonResponse
    {
        $secret = $user->getAppAuthenticationSecret();

        if ($secret === null) {
            return response()->json(['message' => 'Authentification TOTP non configurée.'], 422);
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return response()->json(['message' => 'Le code TOTP est obligatoire.'], 422);
        }

        $google2fa = app(Google2FA::class);

        try {
            $valid = $google2fa->verifyKey($secret, $code, 1);
        } catch (\Throwable) {
            $valid = false;
        }

        if (!$valid) {
            RateLimiter::hit($rateLimitKey, 300);

            return response()->json(['message' => 'Code TOTP invalide ou expiré.'], 422);
        }

        RateLimiter::clear($rateLimitKey);
        $this->markVerified($token);

        return response()->json([
            'message' => 'MFA vérifié avec succès.',
            'mfa_verified' => true,
            'expires_in_minutes' => $this->sessionLifetime(),
        ]);
    }

    /**
     * Verify email OTP code.
     *
     * First call (no code provided): sends OTP email and returns 202.
     * Second call (with code):       verifies the OTP and marks token as verified.
     */
    private function verifyEmailOtp(Request $request, mixed $user, PersonalAccessToken $token, string $rateLimitKey): JsonResponse
    {
        if (!$user->hasEmailAuthentication()) {
            return response()->json(['message' => 'Authentification par email non configurée.'], 422);
        }

        $code = (string) $request->input('code', '');
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
                'message' => 'Code envoyé à '.$this->maskEmail($user->email).'. Saisissez-le dans ce formulaire.',
                'code_sent' => true,
            ], 202);
        }

        // Code provided → verify
        $cachedOtp = Cache::get($otpCacheKey);

        if ($cachedOtp === null || !hash_equals($cachedOtp, $code)) {
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
            'expires_in_minutes' => $this->sessionLifetime(),
        ]);
    }

    private function markVerified(PersonalAccessToken $token): void
    {
        Cache::put(
            'api_mfa_verified_'.$token->getKey(),
            true,
            now()->addMinutes($this->sessionLifetime()),
        );
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 1));

        return $masked.'@'.$domain;
    }
}
