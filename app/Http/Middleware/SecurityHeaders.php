<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply security headers to all responses.
 *
 * - HSTS: force HTTPS in production
 * - CSP: restrict resource origins
 * - X-Frame-Options: clickjacking protection
 * - X-Content-Type-Options: MIME sniffing protection
 * - Referrer-Policy: limit referrer leakage
 * - Permissions-Policy: disable unused browser APIs
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // HSTS — enforce HTTPS for 1 year, include subdomains
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Clickjacking protection
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer leakage prevention
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // CSP — restrictive default; adjust script/style sources as needed
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; frame-ancestors 'none'",
        );

        // Disable unused browser features
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(self), payment=()',
        );

        return $response;
    }
}
