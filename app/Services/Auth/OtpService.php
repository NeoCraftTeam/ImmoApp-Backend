<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;

/**
 * Manages the lifecycle of email OTP verification codes.
 *
 * Extracted from User::sendEmailVerificationNotification() to isolate
 * Cache-based OTP state from the model layer.
 */
final class OtpService
{
    private const int OTP_TTL_MINUTES = 10;

    private const int COOLDOWN_TTL_SECONDS = 60;

    private const string CACHE_PREFIX = 'email_otp_';

    private const string COOLDOWN_PREFIX = 'email_otp_sent_';

    /**
     * Check whether a fresh OTP has already been issued and the cooldown is
     * still active, meaning we should not issue a new one.
     */
    public function isCoolingDown(string $userId): bool
    {
        return Cache::has(self::CACHE_PREFIX.$userId)
            && Cache::has(self::COOLDOWN_PREFIX.$userId);
    }

    /**
     * Generate a new 6-digit OTP for the given user ID, persist it in cache,
     * and arm the per-user cooldown window.
     *
     * @return string The zero-padded 6-digit OTP code.
     */
    public function generate(string $userId): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$userId, $otp, now()->addMinutes(self::OTP_TTL_MINUTES));
        Cache::put(self::COOLDOWN_PREFIX.$userId, true, now()->addSeconds(self::COOLDOWN_TTL_SECONDS));

        return $otp;
    }

    /**
     * Verify whether the supplied code matches the one stored for the given user.
     *
     * Returns `false` when no OTP is cached (expired or never issued).
     */
    public function verify(string $userId, string $code): bool
    {
        $stored = Cache::get(self::CACHE_PREFIX.$userId);

        if ($stored === null) {
            return false;
        }

        return hash_equals($stored, $code);
    }

    /**
     * Invalidate the OTP and its cooldown for the given user — call this after
     * a successful verification to prevent code reuse.
     */
    public function invalidate(string $userId): void
    {
        Cache::forget(self::CACHE_PREFIX.$userId);
        Cache::forget(self::COOLDOWN_PREFIX.$userId);
    }
}
