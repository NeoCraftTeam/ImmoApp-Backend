<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth: verify that the Bearer token's abilities match the required role context.
 *
 * Usage: ->middleware('token.role:agent')  or  ->middleware('token.role:customer')
 *
 * Tokens created by the auth system carry abilities like 'role:agent' or 'role:customer'.
 * This middleware rejects tokens that lack the required role ability, preventing a
 * client-context token from being used on owner-only endpoints (and vice-versa).
 *
 * Tokens with the legacy ['*'] wildcard are allowed through to avoid breaking
 * existing sessions during migration.
 */
final class EnsureTokenMatchesRole
{
    public function handle(Request $request, Closure $next, string $requiredRole = 'agent'): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (
            $token instanceof PersonalAccessToken
            && !$token->can('*')
            && !$token->can("role:{$requiredRole}")
            && !$token->can('role:admin')
        ) {
            return response()->json([
                'message' => 'Token non autorisé pour ce contexte.',
                'code' => 'TOKEN_ROLE_MISMATCH',
            ], 403);
        }

        return $next($request);
    }
}
