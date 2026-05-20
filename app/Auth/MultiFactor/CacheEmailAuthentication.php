<?php

declare(strict_types=1);

namespace App\Auth\MultiFactor;

use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

/**
 * Drop-in replacement for Filament's built-in EmailAuthentication that stores
 * OTP codes in the **cache** (CACHE_STORE=database) instead of the PHP session.
 *
 * Filament's default implementation uses session()->put() / session() to persist
 * the code between the mountUsing callback and the validation rule. In a Livewire
 * context this can silently fail when:
 *   - SESSION_DRIVER=cookie causes race conditions across Livewire's HTTP requests
 *   - A new session is regenerated between the mount and the submit (e.g. remember-me
 *     cookie re-authentication creates a fresh session each request)
 *
 * Using the cache keyed by user ID removes the dependency on session continuity
 * across Livewire requests entirely.
 */
final class CacheEmailAuthentication extends EmailAuthentication
{
    private const string CACHE_PREFIX = 'filament_email_otp_';

    private const string EXPIRY_SUFFIX = '_expires_at';

    private function otpKey(HasEmailAuthentication $user): string
    {
        /** @phpstan-ignore-next-line */
        return self::CACHE_PREFIX.$user->getKey(); // @phpstan-ignore-line
    }

    private function expiryKey(HasEmailAuthentication $user): string
    {
        return $this->otpKey($user).self::EXPIRY_SUFFIX;
    }

    /**
     * Generate OTP, store hash+expiry in cache, send notification.
     *
     * Returns false when rate-limited (same behaviour as parent).
     */
    #[\Override]
    public function sendCode(HasEmailAuthentication $user): bool
    {
        /** @phpstan-ignore-next-line */
        $rateLimitingKey = 'filament-email-authentication:'.$user->getKey(); // @phpstan-ignore-line

        if (RateLimiter::tooManyAttempts($rateLimitingKey, maxAttempts: 5)) {
            return false;
        }

        RateLimiter::hit($rateLimitingKey);

        $code = $this->generateCode();
        $expiryMinutes = $this->getCodeExpiryMinutes();
        $expiresAt = now()->addMinutes($expiryMinutes);

        Cache::put($this->otpKey($user), Hash::make($code), $expiresAt);
        Cache::put($this->expiryKey($user), $expiresAt, $expiresAt);

        if (!method_exists($user, 'notify')) {
            throw new LogicException('Model does not have a notify() method.');
        }

        $user->notify(app($this->getCodeNotification(), [
            'code' => $code,
            'codeExpiryMinutes' => $expiryMinutes,
        ]));

        return true;
    }

    /**
     * Verify OTP from cache.  Clears keys on success (one-time use).
     */
    #[\Override]
    public function verifyCode(string $code): bool
    {
        $user = Filament::auth()->user();

        if (!($user instanceof HasEmailAuthentication)) {
            return false;
        }

        $codeHash = Cache::get($this->otpKey($user));
        $expiresAt = Cache::get($this->expiryKey($user));

        if (
            blank($codeHash)
            || blank($expiresAt)
            || (!Hash::check($code, $codeHash))
            || now()->greaterThan($expiresAt)
        ) {
            return false;
        }

        Cache::forget($this->otpKey($user));
        Cache::forget($this->expiryKey($user));

        return true;
    }
}
