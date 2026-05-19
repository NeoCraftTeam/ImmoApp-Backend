<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $hasWildcard = $token->can('*');
            $hasRole = $token->can("role:{$requiredRole}");

            // Instrumentation (OWASP A01): wildcard tokens bypass role
            // checks. We keep accepting them to avoid breaking active
            // sessions, but emit a telemetry signal so the team can plan a
            // migration once the prod count reaches zero. Log once per
            // request path to keep volume bounded.
            if ($hasWildcard && !$hasRole) {
                Log::warning('auth.token.wildcard_role_bypass', [
                    'token_id' => $token->id,
                    'tokenable_id' => $token->tokenable_id,
                    'required_role' => $requiredRole,
                    'route' => $request->path(),
                ]);
            }

            if (!$hasWildcard && !$hasRole) {
                return response()->json([
                    'message' => 'Token non autorisé pour ce contexte.',
                    'code' => 'TOKEN_ROLE_MISMATCH',
                ], 403);
            }

            return $next($request);
        }

        // Fallback for non-PAT auth (TransientToken from Clerk JWT exchange,
        // session cookie, etc.). Without this, the middleware would silently
        // allow any authenticated user through — breaking the defense-in-
        // depth contract that the route declared `token.role:…`. We instead
        // verify the role on the User model itself, which is the authoritative
        // source for non-PAT auth flows.
        if ($user instanceof User) {
            $userRole = $user->role->value;
            if ($userRole !== $requiredRole) {
                return response()->json([
                    'message' => $user->isAdmin() && $requiredRole === 'agent'
                        ? 'Utilisez le panneau administrateur.'
                        : 'Rôle utilisateur non autorisé pour ce contexte.',
                    'code' => 'USER_ROLE_MISMATCH',
                ], 403);
            }
        }

        return $next($request);
    }
}
