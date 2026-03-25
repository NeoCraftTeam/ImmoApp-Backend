<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as Middleware;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Same as Filament's Authenticate, but users who are logged in without access
 * to the current panel are signed out and redirected to login instead of a raw 403.
 *
 * This avoids "403 | FORBIDDEN" when a bailleur/agency session is reused on the admin subdomain.
 */
final class FilamentAuthenticate extends Middleware
{
    /**
     * @param  array<string>  $guards
     */
    #[\Override]
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (!$guard->check()) {
            $this->unauthenticated($request, $guards);
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser) {
            if (!$user->canAccessPanel($panel)) {
                Auth::guard(Filament::getAuthGuard())->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $this->unauthenticated($request, $guards);
            }

            return;
        }

        if (config('app.env') !== 'local') {
            abort(403);
        }
    }
}
