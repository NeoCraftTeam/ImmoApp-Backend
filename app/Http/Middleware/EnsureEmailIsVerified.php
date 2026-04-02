<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require email verification for authenticated API requests.
 *
 * Passes through:
 *  - Unauthenticated requests (handled by auth:sanctum)
 *  - Auth routes (verification, resend, logout, refresh, me)
 *
 * Returns 403 with email_verification_required flag for all other
 * authenticated requests where email_verified_at is null.
 */
final class EnsureEmailIsVerified
{
    /**
     * Route prefixes that are exempt from email verification.
     * Auth routes must remain accessible to unverified users.
     */
    private const BYPASS_PREFIXES = [
        'api/v1/auth/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Pass through unauthenticated requests
        if ($user === null) {
            return $next($request);
        }

        // Pass through if already verified
        if ($user->email_verified_at !== null) {
            return $next($request);
        }

        // Allow auth-related routes (resend, verify-otp, logout, me, etc.)
        foreach (self::BYPASS_PREFIXES as $prefix) {
            if ($request->is($prefix.'*')) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Votre adresse email doit être vérifiée avant de continuer.',
            'email_verification_required' => true,
            'code' => 'EMAIL_UNVERIFIED',
        ], 403);
    }
}
