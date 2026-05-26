<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * MFA setup / enrolment for any authenticated user.
 *
 * Admins enrol via the Filament panel; this controller mirrors the same
 * capability for the REST API so customers and agents can opt-in to
 * TOTP or email-based step-up MFA from the PWA.
 *
 * Endpoints (all under `/api/v1/auth/mfa/setup/*`, `auth:sanctum`):
 *  - POST /totp/start    → returns ephemeral secret + otpauth URL
 *  - POST /totp/confirm  → user supplies first valid code; secret + recovery codes persisted
 *  - POST /totp/disable  → wipes TOTP + recovery codes (requires current code)
 *  - POST /email/enable  → enables email MFA (sends test code first)
 *  - POST /email/confirm → user supplies the test code; flag flipped on
 *  - POST /email/disable → flag flipped off (requires current code)
 *
 * Security notes:
 *  - The pending TOTP secret is cached server-side under a per-token key
 *    (10 min TTL) so a leaked secret never reaches persistent storage
 *    before the user proves possession by entering a valid TOTP.
 *  - Recovery codes are returned **once** (plaintext) at confirm-time. They
 *    are stored via `User::saveAppAuthenticationRecoveryCodes()`, which
 *    inherits the `encrypted:array` cast from the User model.
 *  - Per-user rate limiter on every endpoint (10/min) to thwart brute-force.
 */
final class UserMfaSetupController
{
    private const PENDING_TOTP_TTL_MIN = 10;

    private const RECOVERY_CODES_COUNT = 8;

    /**
     * Step 1 — generate a TOTP secret + QR otpauth URL.
     *
     * Returns:
     *  - secret (base32)        : show as text/copy-fallback
     *  - otpauth_url            : `otpauth://totp/...` for QR rendering
     *  - holder (account label) : email used inside the authenticator app
     *  - company                : app name shown in the authenticator
     *
     * The secret is **not** persisted yet. It lives in the cache under the
     * current token's ID for 10 minutes.
     */
    public function startTotp(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-totp-start', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        RateLimiter::hit($rateKey, 60);

        if ($user->getAppAuthenticationSecret() !== null) {
            return response()->json([
                'message' => 'TOTP déjà activé. Désactivez-le avant de générer un nouveau secret.',
                'code' => 'MFA_TOTP_ALREADY_ENABLED',
            ], 422);
        }

        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey(32);

        Cache::put(
            $this->pendingTotpKey($user->id),
            $secret,
            now()->addMinutes(self::PENDING_TOTP_TTL_MIN),
        );

        $company = (string) config('app.name', 'KeyHome');
        $holder = $user->getAppAuthenticationHolderName();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $google2fa->getQRCodeUrl($company, $holder, $secret),
            'holder' => $holder,
            'company' => $company,
            'expires_in_minutes' => self::PENDING_TOTP_TTL_MIN,
        ]);
    }

    /**
     * Step 2 — confirm TOTP by entering the first valid code.
     *
     * On success persists the secret and returns single-use recovery codes
     * (shown **once**).
     */
    public function confirmTotp(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-totp-confirm', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:8'],
        ]);

        $pendingSecret = Cache::get($this->pendingTotpKey($user->id));

        if (!is_string($pendingSecret) || $pendingSecret === '') {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'message' => 'Aucune session d\'enrôlement TOTP active. Recommencez depuis le début.',
                'code' => 'MFA_TOTP_NO_PENDING_SETUP',
            ], 422);
        }

        $google2fa = app(Google2FA::class);

        try {
            $valid = $google2fa->verifyKey($pendingSecret, $validated['code'], 1);
        } catch (\Throwable) {
            $valid = false;
        }

        if (!$valid) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'message' => 'Code TOTP invalide ou expiré.',
                'code' => 'MFA_TOTP_INVALID_CODE',
            ], 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->saveAppAuthenticationSecret($pendingSecret);
        $user->saveAppAuthenticationRecoveryCodes($recoveryCodes);

        Cache::forget($this->pendingTotpKey($user->id));
        RateLimiter::clear($rateKey);

        return response()->json([
            'message' => 'TOTP activé avec succès. Conservez vos codes de récupération en lieu sûr.',
            'mfa_method' => 'totp',
            // Plaintext recovery codes are returned **once** — they will not
            // appear again. Frontends should prompt the user to download/print.
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable TOTP. Requires a current valid TOTP code (or a recovery code)
     * so that a stolen Sanctum token alone cannot disable MFA.
     */
    public function disableTotp(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-totp-disable', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        $secret = $user->getAppAuthenticationSecret();

        if ($secret === null) {
            return response()->json([
                'message' => 'TOTP n\'est pas activé sur ce compte.',
                'code' => 'MFA_TOTP_NOT_ENABLED',
            ], 422);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:20'],
        ]);

        if (!$this->verifyTotpOrRecoveryCode($user, $secret, $validated['code'])) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'message' => 'Code invalide.',
                'code' => 'MFA_INVALID_CODE',
            ], 422);
        }

        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);
        RateLimiter::clear($rateKey);

        return response()->json([
            'message' => 'TOTP désactivé.',
            'mfa_method' => 'totp',
            'disabled' => true,
        ]);
    }

    /**
     * Step 1 — request enabling email MFA. Sends a verification code to the
     * user's email; user must confirm it via `confirmEmail`.
     */
    public function enableEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-email-enable', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        RateLimiter::hit($rateKey, 60);

        if ($user->hasEmailAuthentication()) {
            return response()->json([
                'message' => 'L\'authentification par email est déjà activée.',
                'code' => 'MFA_EMAIL_ALREADY_ENABLED',
            ], 422);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(
            $this->pendingEmailKey($user->id),
            $otp,
            now()->addMinutes(10),
        );

        Mail::to($user->email, $user->firstname)
            ->queue(new VerificationCodeMail(
                $otp,
                $request->ip() ?? 'inconnu',
                now()->translatedFormat('d F Y à H:i'),
            ));

        return response()->json([
            'message' => 'Code de vérification envoyé. Saisissez-le pour activer l\'authentification par email.',
            'code_sent' => true,
        ], 202);
    }

    /**
     * Step 2 — confirm email MFA setup with the code received by email.
     */
    public function confirmEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-email-confirm', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $cachedOtp = Cache::get($this->pendingEmailKey($user->id));

        if (!is_string($cachedOtp) || !hash_equals($cachedOtp, $validated['code'])) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'message' => 'Code invalide ou expiré.',
                'code' => 'MFA_EMAIL_INVALID_CODE',
            ], 422);
        }

        $user->toggleEmailAuthentication(true);
        Cache::forget($this->pendingEmailKey($user->id));
        RateLimiter::clear($rateKey);

        return response()->json([
            'message' => 'Authentification par email activée.',
            'mfa_method' => 'email',
        ]);
    }

    /**
     * Disable email MFA. Requires a fresh code sent to email.
     *
     * If no code is supplied (first call), sends one and returns 202.
     * On second call with the code, verifies and flips the flag off.
     */
    public function disableEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-email-disable', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        if (!$user->hasEmailAuthentication()) {
            return response()->json([
                'message' => 'L\'authentification par email n\'est pas activée.',
                'code' => 'MFA_EMAIL_NOT_ENABLED',
            ], 422);
        }

        $code = (string) $request->input('code', '');
        $cacheKey = $this->pendingEmailDisableKey($user->id);

        if ($code === '') {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put($cacheKey, $otp, now()->addMinutes(10));
            RateLimiter::hit($rateKey, 60);

            Mail::to($user->email, $user->firstname)
                ->queue(new VerificationCodeMail(
                    $otp,
                    $request->ip() ?? 'inconnu',
                    now()->translatedFormat('d F Y à H:i'),
                ));

            return response()->json([
                'message' => 'Un code a été envoyé pour confirmer la désactivation.',
                'code_sent' => true,
            ], 202);
        }

        $cachedOtp = Cache::get($cacheKey);

        if (!is_string($cachedOtp) || !hash_equals($cachedOtp, $code)) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'message' => 'Code invalide ou expiré.',
                'code' => 'MFA_EMAIL_INVALID_CODE',
            ], 422);
        }

        $user->toggleEmailAuthentication(false);
        Cache::forget($cacheKey);
        RateLimiter::clear($rateKey);

        return response()->json([
            'message' => 'Authentification par email désactivée.',
            'mfa_method' => 'email',
            'disabled' => true,
        ]);
    }

    /**
     * Try TOTP first, then fall back to single-use recovery codes.
     * Consumed recovery codes are removed from the stored list.
     */
    private function verifyTotpOrRecoveryCode(User $user, string $secret, string $code): bool
    {
        $google2fa = app(Google2FA::class);

        try {
            if ($google2fa->verifyKey($secret, $code, 1)) {
                return true;
            }
        } catch (\Throwable) {
            // fall through to recovery codes
        }

        $codes = $user->getAppAuthenticationRecoveryCodes() ?? [];

        foreach ($codes as $idx => $recoveryCode) {
            if (hash_equals((string) $recoveryCode, $code)) {
                unset($codes[$idx]);
                $user->saveAppAuthenticationRecoveryCodes(array_values($codes));

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            // 4-4 group like Google's recovery codes — easy to copy/paste.
            $codes[] = strtoupper(
                substr(bin2hex(random_bytes(2)), 0, 4)
                .'-'
                .substr(bin2hex(random_bytes(2)), 0, 4)
            );
        }

        return $codes;
    }

    private function pendingTotpKey(string $userId): string
    {
        return 'mfa_pending_totp:'.$userId;
    }

    private function pendingEmailKey(string $userId): string
    {
        return 'mfa_pending_email:'.$userId;
    }

    private function pendingEmailDisableKey(string $userId): string
    {
        return 'mfa_pending_email_disable:'.$userId;
    }

    private function rateKey(string $bucket, string $userId): string
    {
        return $bucket.':'.$userId;
    }

    private function rateLimited(string $key): JsonResponse
    {
        $seconds = RateLimiter::availableIn($key);

        return response()->json([
            'message' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
            'code' => 'RATE_LIMITED',
            'retry_after' => $seconds,
        ], 429);
    }
}
