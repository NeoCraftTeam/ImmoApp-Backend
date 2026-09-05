<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\ConfirmEmailMfaRequest;
use App\Http\Requests\Api\V1\Auth\ConfirmTotpRequest;
use App\Http\Requests\Api\V1\Auth\DisableTotpRequest;
use App\Http\Requests\Api\V1\Auth\RegenerateRecoveryCodesRequest;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Services\Auth\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * MFA setup / enrolment for any authenticated user.
 *
 * Admins enrol via the Filament panel; this controller mirrors the same
 * capability for the REST API so customers and agents can opt-in to
 * TOTP or email-based step-up MFA from the PWA.
 *
 * Endpoints (all under `/api/v1/auth/mfa/setup/*`, `auth:sanctum`):
 *  - POST /totp/start    → returns ephemeral secret + otpauth URL + QR image
 *  - POST /totp/confirm  → user supplies first valid code; secret + recovery codes persisted
 *  - POST /totp/disable  → wipes TOTP + recovery codes (requires current code)
 *  - POST /email/enable  → enables email MFA (sends test code first)
 *  - POST /email/confirm → user supplies the test code; flag flipped on
 *  - POST /email/disable → flag flipped off (requires current code)
 *
 * Security notes:
 *  - The pending TOTP secret is cached server-side under a per-user key
 *    (10 min TTL) so a leaked secret never reaches persistent storage
 *    before the user proves possession by entering a valid TOTP.
 *  - Recovery codes are returned **once** (plaintext) at confirm-time and
 *    persisted bcrypt-hashed by {@see MfaService::saveRecoveryCodes()} — the
 *    same shape the Filament panel writes, so a user can enrol here and
 *    verify there (and vice-versa) with the very same codes.
 *  - Per-user rate limiter on every endpoint (10/min) to thwart brute-force.
 */
final readonly class UserMfaSetupController
{
    private const int PENDING_TOTP_TTL_MIN = 10;

    public function __construct(private MfaService $mfa) {}

    /**
     * Step 1 — generate a TOTP secret + QR otpauth URL.
     *
     * Returns:
     *  - secret (base32)        : show as text/copy-fallback
     *  - otpauth_url            : `otpauth://totp/...` for QR rendering
     *  - qr_code                : ready-to-display `image/svg+xml` data URI
     *  - holder (account label) : email used inside the authenticator app
     *  - company                : app name shown in the authenticator
     *
     * The secret is **not** persisted yet. It lives in the cache under the
     * current user's ID for 10 minutes.
     */
    public function startTotp(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-totp-start', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        RateLimiter::hit($rateKey, 60);

        if ($this->mfa->hasTotp($user)) {
            return response()->json([
                'message' => 'TOTP déjà activé. Désactivez-le avant de générer un nouveau secret.',
                'code' => 'MFA_TOTP_ALREADY_ENABLED',
            ], 422);
        }

        $secret = $this->mfa->generateSecret();

        Cache::put(
            $this->pendingTotpKey($user->id),
            $secret,
            now()->addMinutes(self::PENDING_TOTP_TTL_MIN),
        );

        $company = $this->mfa->brandName();
        $holder = $user->getAppAuthenticationHolderName();
        $otpauthUrl = $this->mfa->otpauthUrl($secret, $holder, $company);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            // Rendered server-side so no client has to ship a QR library; null
            // if rendering failed, in which case the URL/secret still work.
            'qr_code' => $this->mfa->qrCodeDataUri($otpauthUrl),
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
    public function confirmTotp(ConfirmTotpRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-totp-confirm', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        $validated = $request->validated();

        $pendingSecret = Cache::get($this->pendingTotpKey($user->id));

        if (!is_string($pendingSecret) || $pendingSecret === '') {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'message' => 'Aucune session d\'enrôlement TOTP active. Recommencez depuis le début.',
                'code' => 'MFA_TOTP_NO_PENDING_SETUP',
            ], 422);
        }

        // `preventReuse: false` on purpose: burning this timestep would make the
        // code still on screen fail at the very next login, seconds later.
        $valid = $this->mfa->verifyTotpAgainstSecret(
            $pendingSecret,
            (string) $validated['code'],
            preventReuse: false,
        );

        if (!$valid) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'message' => 'Code TOTP invalide ou expiré.',
                'code' => 'MFA_TOTP_INVALID_CODE',
            ], 422);
        }

        $recoveryCodes = $this->mfa->generateRecoveryCodes();

        $user->saveAppAuthenticationSecret($pendingSecret);
        $this->mfa->saveRecoveryCodes($user, $recoveryCodes);

        Cache::forget($this->pendingTotpKey($user->id));
        RateLimiter::clear($rateKey);

        return response()->json([
            'message' => 'TOTP activé avec succès. Conservez vos codes de récupération en lieu sûr.',
            'mfa_method' => 'totp',
            // Plaintext recovery codes are returned **once** — only their bcrypt
            // hashes are stored. Frontends should prompt the user to
            // download/print them before leaving the screen.
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable TOTP. Requires a current valid TOTP code (or a recovery code)
     * so that a stolen Sanctum token alone cannot disable MFA.
     */
    public function disableTotp(DisableTotpRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-totp-disable', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        if (!$this->mfa->hasTotp($user)) {
            return response()->json([
                'message' => 'TOTP n\'est pas activé sur ce compte.',
                'code' => 'MFA_TOTP_NOT_ENABLED',
            ], 422);
        }

        $validated = $request->validated();

        if ($this->mfa->verifyTotpOrRecoveryCode($user, (string) $validated['code']) === null) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'message' => 'Code invalide.',
                'code' => 'MFA_INVALID_CODE',
            ], 422);
        }

        $user->saveAppAuthenticationSecret(null);
        $this->mfa->saveRecoveryCodes($user, null);
        RateLimiter::clear($rateKey);

        return response()->json([
            'message' => 'TOTP désactivé.',
            'mfa_method' => 'totp',
            'disabled' => true,
        ]);
    }

    /**
     * Regenerate the single-use recovery codes for an enrolled user.
     *
     * Requires a current valid TOTP code (or an existing recovery code) — same
     * guard as `disableTotp` — so a stolen Sanctum token alone cannot silently
     * rotate (and thereby invalidate) the legitimate owner's codes. The fresh
     * codes are returned **once**; the previous set is discarded immediately.
     */
    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-totp-recovery-regenerate', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        if (!$this->mfa->hasTotp($user)) {
            return response()->json([
                'message' => 'TOTP n\'est pas activé sur ce compte.',
                'code' => 'MFA_TOTP_NOT_ENABLED',
            ], 422);
        }

        $validated = $request->validated();

        if ($this->mfa->verifyTotpOrRecoveryCode($user, (string) $validated['code']) === null) {
            RateLimiter::hit($rateKey, 300);

            return response()->json([
                'message' => 'Code invalide.',
                'code' => 'MFA_INVALID_CODE',
            ], 422);
        }

        $recoveryCodes = $this->mfa->generateRecoveryCodes();
        $this->mfa->saveRecoveryCodes($user, $recoveryCodes);
        RateLimiter::clear($rateKey);

        return response()->json([
            'message' => 'Nouveaux codes de récupération générés. Conservez-les en lieu sûr — les anciens ne sont plus valides.',
            'mfa_method' => 'totp',
            // Plaintext recovery codes are returned **once**.
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Step 1 — request enabling email MFA. Sends a verification code to the
     * user's email; user must confirm it via `confirmEmail`.
     */
    public function enableEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
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
    public function confirmEmail(ConfirmEmailMfaRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $rateKey = $this->rateKey('setup-email-confirm', $user->id);

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return $this->rateLimited($rateKey);
        }

        $validated = $request->validated();

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

        if (!$user instanceof User) {
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
