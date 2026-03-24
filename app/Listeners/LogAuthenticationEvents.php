<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;

/**
 * Logs authentication events to both the Laravel log and Spatie Activity Log.
 *
 * This listener covers all 5 core auth events: Login, Logout, Failed,
 * Lockout, and PasswordReset — creating an immutable security audit trail.
 */
class LogAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        if (!$user instanceof User) {
            return;
        }

        Log::channel('security')->info('User logged in', [
            'user_id' => $user->getAuthIdentifier(),
            'email' => $user->email ?? 'N/A',
            'guard' => $event->guard,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        activity('security')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'action' => 'login',
                'guard' => $event->guard,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('User logged in');
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if (!$user instanceof User) {
            return;
        }

        Log::channel('security')->info('User logged out', [
            'user_id' => $user->getAuthIdentifier(),
            'email' => $user->email ?? 'N/A',
            'ip' => request()->ip(),
        ]);

        activity('security')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'action' => 'logout',
                'ip' => request()->ip(),
            ])
            ->log('User logged out');
    }

    public function handleFailed(Failed $event): void
    {
        Log::channel('security')->warning('Failed login attempt', [
            'email' => $event->credentials['email'] ?? 'unknown',
            'guard' => $event->guard,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $failedUser = $event->user;
        if ($failedUser instanceof User) {
            activity('security')
                ->performedOn($failedUser)
                ->withProperties([
                    'action' => 'login_failed',
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log('Failed login attempt');
        }
    }

    public function handleLockout(Lockout $event): void
    {
        $request = $event->request;

        Log::channel('security')->warning('Account locked out', [
            'email' => $request->input('email', 'unknown'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user;
        if (!$user instanceof User) {
            return;
        }

        Log::channel('security')->info('Password reset completed', [
            'user_id' => $user->getAuthIdentifier(),
            'email' => $user->email ?? 'N/A',
            'ip' => request()->ip(),
        ]);

        activity('security')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'action' => 'password_reset',
                'ip' => request()->ip(),
            ])
            ->log('Password reset completed');
    }
}
