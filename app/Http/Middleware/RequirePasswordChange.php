<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Filament\Admin\Pages\ForcePasswordChange;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    /**
     * Handle an incoming request.
     * Redirects admin users who must change their password to the force-password-change page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasMustChangePassword()) {
            return $next($request);
        }

        $path = $request->path();
        $allowedPaths = [
            'force-password-change',
            'multi-factor-authentication/set-up',
            'multi-factor-authentication/challenge',
        ];
        foreach ($allowedPaths as $allowed) {
            if (str_contains($path, $allowed)) {
                return $next($request);
            }
        }

        return redirect()->to(ForcePasswordChange::getUrl());
    }
}
