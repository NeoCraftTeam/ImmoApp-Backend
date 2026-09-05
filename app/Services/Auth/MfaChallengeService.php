<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTOs\MfaChallenge;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Short-lived, single-use tickets for a login that still owes a second factor.
 *
 * The first factor (password, OAuth identity, Clerk JWT) is verified as usual,
 * but instead of a Sanctum token the client receives an opaque 64-char challenge
 * token. Nothing about the account is reachable with it: it only unlocks
 * `POST /api/v1/auth/mfa/challenge`, and only for as long as
 * `auth.mfa_challenge_ttl` minutes.
 *
 * Only the SHA-256 digest of the token is stored, so a dump of the cache does
 * not let anyone finish a pending login. Wrong codes are counted inside the
 * cached payload — after `auth.mfa_challenge_max_attempts` the ticket is
 * destroyed and the user starts the login over, which also re-arms the
 * per-IP login throttle.
 */
final readonly class MfaChallengeService
{
    public const string SOURCE_PASSWORD = 'password';

    public const string SOURCE_OAUTH = 'oauth';

    public const string SOURCE_OAUTH_LINK = 'oauth_link';

    public const string SOURCE_CLERK = 'clerk';

    public const string CODE_REQUIRED = 'MFA_CHALLENGE_REQUIRED';

    public const string CODE_INVALID = 'MFA_CHALLENGE_INVALID';

    public const string CODE_EXHAUSTED = 'MFA_CHALLENGE_EXHAUSTED';

    public const string CODE_BAD_CODE = 'MFA_INVALID_CODE';

    private const string CACHE_PREFIX = 'mfa_challenge:';

    private const string OTP_PREFIX = 'mfa_challenge_otp:';

    private const string OTP_COOLDOWN_PREFIX = 'mfa_challenge_otp_cooldown:';

    private const int OTP_TTL_MINUTES = 10;

    private const int OTP_COOLDOWN_SECONDS = 60;

    public function __construct(private MfaService $mfa) {}

    public function ttlMinutes(): int
    {
        return max(1, (int) config('auth.mfa_challenge_ttl', 10));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('auth.mfa_challenge_max_attempts', 5));
    }

    /**
     * Per-IP + per-user budget a challenge spends when codes are guessed.
     *
     * Password login passes its own `login-attempts:` key so the two budgets are
     * one: minting a fresh challenge cannot be used to reset the counter, which
     * is what makes a ±4-minute TOTP window safe to brute-force against.
     */
    public function throttleKeyFor(User $user, ?string $ip): string
    {
        return 'mfa-challenge:'.($ip ?? 'unknown').'|'.$user->getKey();
    }

    /**
     * Mint a challenge for a user who has just cleared the first factor.
     *
     * @param  string  $source  One of the `SOURCE_*` constants.
     * @param  bool  $stateful  The caller had a session (SPA/web), so the web
     *                          guard must be logged in once the challenge passes.
     * @param  string|null  $throttleKey  Existing RateLimiter key to keep spending;
     *                                    defaults to {@see throttleKeyFor()}.
     */
    public function issue(
        User $user,
        string $source,
        ?string $loginContext = null,
        ?string $provider = null,
        bool $isNewUser = false,
        bool $stateful = false,
        ?string $throttleKey = null,
        ?string $ip = null,
    ): MfaChallenge {
        $token = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes($this->ttlMinutes());
        $throttleKey ??= $this->throttleKeyFor($user, $ip);

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->getKey(),
            'source' => $source,
            'login_context' => $loginContext,
            'provider' => $provider,
            'is_new_user' => $isNewUser,
            'stateful' => $stateful,
            'attempts' => 0,
            'throttle_key' => $throttleKey,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        return new MfaChallenge(
            user: $user,
            source: $source,
            loginContext: $loginContext,
            provider: $provider,
            isNewUser: $isNewUser,
            stateful: $stateful,
            attempts: 0,
            throttleKey: $throttleKey,
            token: $token,
        );
    }

    /**
     * Resolve a challenge token, or null when it is unknown, expired, or points
     * at an account that has since been deleted or deactivated.
     */
    public function retrieve(#[SensitiveParameter] string $token): ?MfaChallenge
    {
        $payload = Cache::get($this->cacheKey($token));

        if (!is_array($payload) || !isset($payload['user_id'], $payload['source'])) {
            return null;
        }

        $user = User::query()->whereKey($payload['user_id'])->first();

        // A ticket is only as good as the account behind it: deleting or
        // deactivating the user between the two steps kills the pending login.
        if ($user === null || (isset($user->is_active) && !$user->is_active)) {
            $this->forget($token);

            return null;
        }

        return new MfaChallenge(
            user: $user,
            source: (string) $payload['source'],
            loginContext: isset($payload['login_context']) ? (string) $payload['login_context'] : null,
            provider: isset($payload['provider']) ? (string) $payload['provider'] : null,
            isNewUser: (bool) ($payload['is_new_user'] ?? false),
            stateful: (bool) ($payload['stateful'] ?? false),
            attempts: (int) ($payload['attempts'] ?? 0),
            throttleKey: (string) ($payload['throttle_key'] ?? $this->throttleKeyFor($user, null)),
            token: $token,
        );
    }

    /**
     * Record a wrong code.
     *
     * @return int Attempts left; 0 means the challenge has just been destroyed.
     */
    public function registerFailure(MfaChallenge $challenge): int
    {
        $token = (string) $challenge->token;
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (!is_array($payload)) {
            return 0;
        }

        $attempts = (int) ($payload['attempts'] ?? 0) + 1;
        $remaining = max(0, $this->maxAttempts() - $attempts);

        if ($remaining === 0) {
            $this->forget($token);

            return 0;
        }

        $payload['attempts'] = $attempts;

        // Re-put with the original deadline: failing must never extend the ticket.
        Cache::put($key, $payload, Carbon::createFromTimestamp((int) ($payload['expires_at'] ?? Carbon::now()->addMinutes($this->ttlMinutes())->getTimestamp())));

        return $remaining;
    }

    /** Burn the challenge — called as soon as a code is accepted. */
    public function consume(MfaChallenge $challenge): void
    {
        $this->forget((string) $challenge->token);
    }

    public function forget(#[SensitiveParameter] string $token): void
    {
        Cache::forget($this->cacheKey($token));
        Cache::forget($this->otpKey($token));
        Cache::forget($this->otpCooldownKey($token));
    }

    // ─── HTTP shape ───────────────────────────────────────────────────────

    /**
     * The single body every login surface returns when a second factor is due.
     *
     * @return array<string, mixed>
     */
    public function payload(MfaChallenge $challenge): array
    {
        return [
            'message' => 'Vérification en deux étapes requise.',
            'mfa_required' => true,
            'code' => self::CODE_REQUIRED,
            'mfa_token' => $challenge->token,
            'methods' => $this->mfa->enabledMethods($challenge->user),
            'has_recovery_codes' => $this->mfa->remainingRecoveryCodeCount($challenge->user) > 0,
            'masked_email' => $this->mfa->maskEmail((string) $challenge->user->email),
            'expires_in_minutes' => $this->ttlMinutes(),
            'attempts_remaining' => max(0, $this->maxAttempts() - $challenge->attempts),
        ];
    }

    /**
     * 403 rather than a 2xx on purpose: an already-deployed mobile build that
     * knows nothing about MFA fails loudly instead of silently treating the
     * challenge as a successful login with a missing token.
     */
    public function response(MfaChallenge $challenge): JsonResponse
    {
        return response()->json($this->payload($challenge), 403);
    }

    // ─── Email OTP (for accounts whose second factor is email) ────────────

    public function emailOtpCooldownRemaining(MfaChallenge $challenge): int
    {
        $until = Cache::get($this->otpCooldownKey((string) $challenge->token));

        if (!is_int($until)) {
            return 0;
        }

        return max(0, $until - Carbon::now()->getTimestamp());
    }

    /**
     * Mail a fresh 6-digit code to the challenge owner.
     *
     * Queued like every other transactional mail in the app; the caller answers
     * 202 so the client knows to show the code input.
     */
    public function sendEmailOtp(MfaChallenge $challenge, ?string $ip = null): void
    {
        $token = (string) $challenge->token;
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->otpKey($token), $otp, Carbon::now()->addMinutes(self::OTP_TTL_MINUTES));
        Cache::put(
            $this->otpCooldownKey($token),
            Carbon::now()->addSeconds(self::OTP_COOLDOWN_SECONDS)->getTimestamp(),
            Carbon::now()->addSeconds(self::OTP_COOLDOWN_SECONDS),
        );

        Mail::to($challenge->user->email, $challenge->user->firstname)
            ->queue(new VerificationCodeMail(
                $otp,
                $ip ?? 'inconnu',
                Carbon::now()->translatedFormat('d F Y à H:i'),
            ));
    }

    public function verifyEmailOtp(MfaChallenge $challenge, #[SensitiveParameter] string $code): bool
    {
        $token = (string) $challenge->token;
        $expected = Cache::get($this->otpKey($token));

        if (!is_string($expected) || !hash_equals($expected, trim($code))) {
            return false;
        }

        Cache::forget($this->otpKey($token));
        Cache::forget($this->otpCooldownKey($token));

        return true;
    }

    private function cacheKey(#[SensitiveParameter] string $token): string
    {
        return self::CACHE_PREFIX.hash('sha256', $token);
    }

    private function otpKey(#[SensitiveParameter] string $token): string
    {
        return self::OTP_PREFIX.hash('sha256', $token);
    }

    private function otpCooldownKey(#[SensitiveParameter] string $token): string
    {
        return self::OTP_COOLDOWN_PREFIX.hash('sha256', $token);
    }
}
