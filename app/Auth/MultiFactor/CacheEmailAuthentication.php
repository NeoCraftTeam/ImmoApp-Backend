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
 *
 * Root cause of the "code always invalid" bug (fixed here):
 *   During Filament 4's MFA challenge, `Filament::auth()->user()` returns null
 *   because the guard is not yet established — the user passed credentials but
 *   has not completed MFA. `verifyCode()` therefore cannot compute the OTP
 *   cache key and always returns false.
 *
 * Fix: `sendCode()` writes a short-lived session→user_id mapping to cache.
 *   `verifyCode()` first tries `Filament::auth()->user()` (works when guard is
 *   available), then falls back to loading the user via the cached mapping.
 */
final class CacheEmailAuthentication extends EmailAuthentication
{
    private const string CACHE_PREFIX = 'filament_email_otp_';

    private const string EXPIRY_SUFFIX = '_expires_at';

    /** Maps current session ID → pending user ID during MFA challenge. */
    private const string SESSION_MAP_PREFIX = 'filament_email_otp_sess_';

    private function otpKey(HasEmailAuthentication $user): string
    {
        /** @phpstan-ignore-next-line */
        return self::CACHE_PREFIX.$user->getKey(); // @phpstan-ignore-line
    }

    private function expiryKey(HasEmailAuthentication $user): string
    {
        return $this->otpKey($user).self::EXPIRY_SUFFIX;
    }

    /** Cache key that maps the current session to the pending user ID. */
    private function sessionUserKey(): string
    {
        return self::SESSION_MAP_PREFIX.session()->getId();
    }

    /**
     * Generate OTP, store hash+expiry in cache, send notification.
     * Also caches the session→user_id mapping so verifyCode() can resolve
     * the user even when Filament::auth()->user() is null (MFA challenge).
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

        // Store session → user_id so verifyCode() can look up the user
        // when Filament::auth()->user() is null during the challenge step.
        /** @phpstan-ignore-next-line */
        Cache::put($this->sessionUserKey(), $user->getKey(), $expiresAt); // @phpstan-ignore-line

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
     * Verify OTP from cache.  Clears all keys on success (one-time use).
     */
    #[\Override]
    public function verifyCode(string $code): bool
    {
        // Primary path: guard is already set (e.g. "remember me" session).
        $user = Filament::auth()->user();

        // Fallback: during the MFA challenge Filament::auth()->user() is null
        // because the guard isn't established until MFA succeeds. Resolve the
        // user via the session→user_id cache mapping written in sendCode().
        if (!($user instanceof HasEmailAuthentication)) {
            $userId = Cache::get($this->sessionUserKey());

            if ($userId !== null) {
                /** @phpstan-ignore method.notFound */
                $user = Filament::auth()->getProvider()->retrieveById($userId);
            }
        }

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
        Cache::forget($this->sessionUserKey());

        return true;
    }
}
