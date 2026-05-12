<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require MFA verification for admin API requests.
 *
 * When an admin user has TOTP or email MFA configured and accesses
 * a protected route, this middleware enforces that the current token
 * has been MFA-verified (via POST /api/v1/auth/mfa/verify).
 *
 * The MFA verification is cached per token ID for the duration of
 * MFA_API_SESSION_LIFETIME minutes (default: 480 = 8 hours).
 */
final class RequireApiMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Only enforce MFA for admin users
        if (!$user->isAdmin()) {
            return $next($request);
        }

        // Only enforce if the user has MFA configured
        $hasMfaConfigured = $user->getAppAuthenticationSecret() !== null
            || $user->hasEmailAuthentication();

        if (!$hasMfaConfigured) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if (!$token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $cacheKey = 'api_mfa_verified_'.$token->getKey();

        if (cache()->has($cacheKey)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Vérification MFA requise pour accéder à cette ressource.',
            'mfa_required' => true,
            'code' => 'MFA_REQUIRED',
            'verify_url' => url('/api/v1/auth/mfa/verify'),
        ], 403);
    }
}
