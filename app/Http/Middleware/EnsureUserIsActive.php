<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user's account is active.
 *
 * Deactivated users should not be able to perform any API operations,
 * even if they hold a valid Sanctum token.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            return response()->json([
                'message' => 'Votre compte a été désactivé. Veuillez contacter le support.',
            ], 403);
        }

        return $next($request);
    }
}
