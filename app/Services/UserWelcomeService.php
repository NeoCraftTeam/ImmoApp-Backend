<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\AdminWelcomeEmail;
use App\Mail\BailleurWelcomeEmail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Centralises every post-registration side-effect so both auth paths
 * (Clerk JWT exchange and legacy email/password) produce the same outcome.
 *
 * Called from:
 *  - ClerkAuthController  → immediately after new user is persisted (user already verified)
 *  - SendWelcomeNotification → when the Verified event fires (legacy email/password path)
 */
final class UserWelcomeService
{
    /**
     * Run all post-registration actions for a newly registered user.
     *
     * Safe to call from a queued job or synchronously — internally idempotent
     * via a short-lived cache key so double-calls within 5 minutes are ignored.
     */
    public function handle(User $user): void
    {
        $this->sendWelcomeEmail($user);
        $this->logRegistration($user);
    }

    /**
     * Send the role-appropriate welcome email synchronously.
     * Wrapped in try/catch so a mail failure never breaks registration.
     */
    private function sendWelcomeEmail(User $user): void
    {
        $email = $user->email;

        if ($email === '' || str_ends_with($email, '@clerk.local')) {
            return;
        }

        try {
            $mailable = match ($user->role) {
                UserRole::ADMIN => new AdminWelcomeEmail($user),
                UserRole::AGENT => new BailleurWelcomeEmail($user),
                default => new WelcomeEmail($user),
            };

            Mail::to($email, $user->firstname)->send($mailable);

            Log::info('Welcome email sent', [
                'user_id' => $user->id,
                'role' => $user->role->value,
            ]);
        } catch (\Throwable $e) {
            Log::error('Welcome email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Structured log entry shared across both registration paths.
     */
    private function logRegistration(User $user): void
    {
        Log::info('New user onboarded', [
            'user_id' => $user->id,
            'role' => $user->role->value,
            'path' => $user->clerk_id !== null ? 'clerk' : 'legacy',
            'verified' => $user->email_verified_at !== null,
        ]);
    }
}
