<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tries to authenticate the user via Sanctum without blocking.
 * If a valid Bearer token is present, the user is resolved so
 * $request->user() works. If not, the request continues as a guest.
 */
final class OptionalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            try {
                $user = auth()->guard('sanctum')->user();
                if ($user) {
                    auth()->setUser($user);
                }
            } catch (\Throwable) {
                // Token invalid or expired — continue as guest
            }
        }

        return $next($request);
    }
}
