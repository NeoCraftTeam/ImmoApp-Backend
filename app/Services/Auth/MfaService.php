<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Http\Middleware\RequireApiMfa;
use App\Models\User;
use App\Services\Tour\QrCodeService;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FAQRCode\Google2FA;
use SensitiveParameter;
use Throwable;

/**
 * Shared multi-factor primitives for the REST API.
 *
 * Deliberately mirrors the semantics of Filament's
 * {@see AppAuthentication} so that a user who
 * enrols in the admin panel and a user who enrols through the API end up with
 * *interchangeable* state on the very same `users` columns:
 *
 *  - recovery codes are stored bcrypt-hashed (`Hash::make`) and consumed under
 *    a cache lock + `lockForUpdate` transaction;
 *  - TOTP verification uses `verifyKeyNewer()` against the shared cache key
 *    `filament.app_authentication_codes.{md5(secret)}`, so a code accepted by
 *    the panel cannot be replayed against the API (and vice-versa);
 *  - the accepted window is ±4 minutes (8 timesteps) like the panel, instead
 *    of the ±30 s the API used to allow.
 *
 * A user enrolled *before* this class existed has plaintext recovery codes;
 * {@see verifyRecoveryCode()} keeps a constant-time fallback for them so
 * nobody is locked out. Codes are re-hashed as soon as they are regenerated.
 */
final readonly class MfaService
{
    public const string METHOD_TOTP = 'totp';

    public const string METHOD_EMAIL = 'email';

    public const string METHOD_RECOVERY = 'recovery';

    /** Unambiguous alphabet — no O/0, I/1 or L, so codes can be read aloud. */
    private const string RECOVERY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const int RECOVERY_CODE_COUNT = 8;

    private const int RECOVERY_GROUP_LENGTH = 5;

    public function __construct(private Google2FA $google2fa) {}

    // ─── Enrolment state ──────────────────────────────────────────────────

    public function hasTotp(User $user): bool
    {
        return $user->getAppAuthenticationSecret() !== null;
    }

    public function hasEmail(User $user): bool
    {
        return $user->hasEmailAuthentication();
    }

    /** True as soon as the user asked for a second factor at login. */
    public function isEnabled(User $user): bool
    {
        return $this->hasTotp($user) || $this->hasEmail($user);
    }

    /**
     * Methods the user can actually complete a challenge with, most secure first.
     *
     * @return list<string>
     */
    public function enabledMethods(User $user): array
    {
        return array_values(array_filter([
            $this->hasTotp($user) ? self::METHOD_TOTP : null,
            $this->hasEmail($user) ? self::METHOD_EMAIL : null,
        ]));
    }

    public function remainingRecoveryCodeCount(User $user): int
    {
        return count($user->getAppAuthenticationRecoveryCodes() ?? []);
    }

    // ─── Enrolment helpers ────────────────────────────────────────────────

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function brandName(): string
    {
        return (string) config('app.name', 'KeyHome');
    }

    public function otpauthUrl(#[SensitiveParameter] string $secret, string $holder, ?string $company = null): string
    {
        return $this->google2fa->getQRCodeUrl($company ?? $this->brandName(), $holder, $secret);
    }

    /**
     * Square black-on-white QR as an inline `image/svg+xml` data URI.
     *
     * Rendered server-side with chillerlan/php-qrcode (already a direct
     * dependency, also used by {@see QrCodeService}) so no
     * client has to ship a QR library, and every surface — web, iOS, Android —
     * displays a byte-identical code. Returns null rather than throwing: a
     * failed render must not block enrolment, the `otpauth_url` and the
     * base32 secret are always returned alongside as fallbacks.
     */
    public function qrCodeDataUri(string $otpauthUrl): ?string
    {
        try {
            $svg = (string) new QRCode(new QROptions([
                'outputType' => QROutputInterface::MARKUP_SVG,
                // Authenticator apps scan from a phone held over a screen:
                // medium ECC keeps the matrix small (fewer modules = larger
                // modules at the same pixel size) while tolerating glare.
                'eccLevel' => EccLevel::M,
                'scale' => 8,
                'addQuietzone' => true,
                'quietzoneSize' => 4,
                'svgAddXmlHeader' => false,
                'svgUseFillAttributes' => true,
                'connectPaths' => true,
                'drawCircularModules' => false,
                'cssClass' => 'kh-mfa-qr',
                'outputBase64' => false,
                'moduleValues' => [
                    QRMatrix::M_DATA_DARK => '#000000',
                    QRMatrix::M_FINDER_DARK => '#000000',
                    QRMatrix::M_ALIGNMENT_DARK => '#000000',
                    QRMatrix::M_TIMING_DARK => '#000000',
                    QRMatrix::M_FINDER_DOT => '#000000',
                    QRMatrix::M_DARKMODULE => '#000000',
                    QRMatrix::M_DATA => '#FFFFFF',
                    QRMatrix::M_FINDER => '#FFFFFF',
                    QRMatrix::M_ALIGNMENT => '#FFFFFF',
                    QRMatrix::M_TIMING => '#FFFFFF',
                ],
            ]))->render($otpauthUrl);
        } catch (Throwable) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Fresh plaintext recovery codes. Hash them with {@see saveRecoveryCodes()}
     * before storing; the plaintext is shown to the user exactly once.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = $this->randomRecoveryGroup().'-'.$this->randomRecoveryGroup();
        }

        return $codes;
    }

    /**
     * Persist recovery codes bcrypt-hashed — the same shape the Filament panel
     * writes, so both enrolment surfaces stay interchangeable.
     *
     * @param  list<string>|null  $plaintextCodes
     */
    public function saveRecoveryCodes(User $user, #[SensitiveParameter] ?array $plaintextCodes): void
    {
        if ($plaintextCodes === null) {
            $user->saveAppAuthenticationRecoveryCodes(null);

            return;
        }

        $user->saveAppAuthenticationRecoveryCodes(array_map(
            fn (#[SensitiveParameter] string $code): string => Hash::make($code),
            $plaintextCodes,
        ));
    }

    // ─── Verification ─────────────────────────────────────────────────────

    /**
     * Accepted drift, in 30-second timesteps, either side of the current one.
     * 8 = ±4 minutes, matching the admin panel.
     */
    public function codeWindow(): int
    {
        return max(1, (int) config('auth.mfa_totp_window', 8));
    }

    /**
     * Verify a TOTP against the user's persisted secret.
     *
     * Replay is prevented for real: the cache records the *timestep* of the
     * last accepted code, so RFC 6238 §5.2 holds — a successful verification
     * rejects that code and every earlier one, not merely a literal repeat.
     */
    public function verifyTotp(User $user, #[SensitiveParameter] string $code): bool
    {
        $secret = $user->getAppAuthenticationSecret();

        if ($secret === null || $secret === '') {
            return false;
        }

        return $this->verifyTotpAgainstSecret($secret, $code);
    }

    /**
     * Verify a TOTP against an arbitrary secret — used during enrolment, where
     * the secret is still pending in the cache and not yet persisted.
     *
     * @param  bool  $preventReuse  Disable only for the enrolment confirmation,
     *                              where burning the timestep would make the
     *                              very next login retry the same code and fail.
     */
    public function verifyTotpAgainstSecret(
        #[SensitiveParameter] string $secret,
        #[SensitiveParameter] string $code,
        bool $preventReuse = true,
    ): bool {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        if (!$preventReuse) {
            return $this->safely(fn (): bool => (bool) $this->google2fa->verifyKey($secret, $code, $this->codeWindow()));
        }

        // Key deliberately excludes the code itself: it records the timestep of
        // the last accepted code. Identical to Filament's key so the panel and
        // the API share one replay ledger.
        $cacheKey = 'filament.app_authentication_codes.'.md5($secret);

        $verify = function () use ($cacheKey, $secret, $code): bool {
            $timestamp = $this->google2fa->verifyKeyNewer(
                $secret,
                $code,
                Cache::get($cacheKey),
                $this->codeWindow(),
            );

            if ($timestamp === false) {
                return false;
            }

            if ($timestamp === true) {
                $timestamp = $this->google2fa->getTimestamp();
            }

            Cache::put($cacheKey, $timestamp, ($this->codeWindow() + 1) * 60);

            return true;
        };

        // Not every cache store supports locks, and this runs on the login
        // path: fall back to verifying without one rather than refusing a login.
        if (!Cache::getStore() instanceof LockProvider) {
            return $this->safely($verify);
        }

        return $this->safely(fn (): bool => (bool) Cache::lock("{$cacheKey}.lock", 10)->block(10, $verify));
    }

    /**
     * Consume a single-use recovery code.
     *
     * Serialised with a cache lock **and** a `lockForUpdate` transaction so two
     * concurrent requests cannot both spend the same code. Accepts the legacy
     * plaintext codes issued before recovery codes were hashed.
     */
    public function verifyRecoveryCode(User $user, #[SensitiveParameter] string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        $lockKey = 'mfa.recovery_codes.'.md5($user::class.':'.$user->getKey());

        $consume = fn (): bool => DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return false;
            }

            $stored = $locked->getAppAuthenticationRecoveryCodes() ?? [];

            if ($stored === []) {
                return false;
            }

            $remaining = [];
            $matched = false;

            foreach ($stored as $storedCode) {
                if (!$matched && $this->recoveryCodeMatches($code, (string) $storedCode)) {
                    $matched = true;

                    continue;
                }

                $remaining[] = $storedCode;
            }

            if (!$matched) {
                return false;
            }

            $locked->saveAppAuthenticationRecoveryCodes($remaining);

            // Keep the in-memory instance consistent with the row we just wrote.
            $user->setAttribute('app_authentication_recovery_codes', $remaining);
            $user->syncOriginalAttribute('app_authentication_recovery_codes');

            return true;
        });

        if (!Cache::getStore() instanceof LockProvider) {
            return $consume();
        }

        return (bool) Cache::lock($lockKey, 10)->block(10, $consume);
    }

    /**
     * Try TOTP, then recovery codes.
     *
     * @return self::METHOD_TOTP|self::METHOD_RECOVERY|null The method that
     *                                                      succeeded, or null.
     */
    public function verifyTotpOrRecoveryCode(User $user, #[SensitiveParameter] string $code): ?string
    {
        if ($this->verifyTotp($user, $code)) {
            return self::METHOD_TOTP;
        }

        if ($this->verifyRecoveryCode($user, $code)) {
            return self::METHOD_RECOVERY;
        }

        return null;
    }

    // ─── Misc ─────────────────────────────────────────────────────────────

    /**
     * How long (minutes) a Sanctum token stays "MFA-verified" for
     * {@see RequireApiMfa}.
     */
    public function apiSessionLifetime(): int
    {
        return max(1, (int) config('auth.mfa_api_session_lifetime', 480));
    }

    /**
     * Flag a token as having already cleared a second factor.
     *
     * Called after a login challenge succeeds so an admin who just typed a TOTP
     * is not immediately asked for another one by `RequireApiMfa`.
     */
    public function markTokenVerified(int|string $tokenId): void
    {
        Cache::put('api_mfa_verified_'.$tokenId, true, now()->addMinutes($this->apiSessionLifetime()));
    }

    /**
     * `j***@example.com` — enough for the user to recognise the inbox, not
     * enough to disclose an address they do not already own.
     */
    public function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return str_repeat('*', max(3, mb_strlen($email)));
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }

    private function recoveryCodeMatches(#[SensitiveParameter] string $candidate, #[SensitiveParameter] string $stored): bool
    {
        if (Hash::isHashed($stored)) {
            return Hash::check($candidate, $stored);
        }

        // Legacy plaintext code (enrolled before hashing landed).
        return hash_equals($stored, $candidate);
    }

    private function randomRecoveryGroup(): string
    {
        $alphabet = self::RECOVERY_ALPHABET;
        $max = mb_strlen($alphabet) - 1;
        $group = '';

        for ($i = 0; $i < self::RECOVERY_GROUP_LENGTH; $i++) {
            $group .= $alphabet[random_int(0, $max)];
        }

        return $group;
    }

    /**
     * Google2FA throws on malformed secrets and non-numeric codes; a bad code
     * is a failed verification, never a 500.
     *
     * @param  callable(): bool  $callback
     */
    private function safely(callable $callback): bool
    {
        try {
            return $callback();
        } catch (Throwable) {
            return false;
        }
    }
}
