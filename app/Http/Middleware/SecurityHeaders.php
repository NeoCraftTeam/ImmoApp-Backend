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

        // CSP — API endpoints receive a strict deny-all (no HTML rendered).
        // The Swagger UI documentation page is excluded and gets its own policy.
        // Web/Filament panel responses receive a nonce-based policy.
        $isApiRoute    = $request->is('api/*') && !$request->routeIs('ads.pdf');
        $isDocsRoute   = $request->is('api/documentation') || $request->is('docs/*') || $request->is('api/oauth2-callback');

        if ($isApiRoute && !$isDocsRoute) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'");
        } elseif ($isDocsRoute) {
            // Swagger UI requires same-origin scripts, CDN scripts (js-cookie), and inline styles.
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; "
                    ."script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
                    ."style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
                    ."img-src 'self' data: https:; "
                    ."font-src 'self' data: https://cdn.jsdelivr.net; "
                    ."connect-src 'self' https:; "
                    ."frame-ancestors 'none'",
            );
        } else {
            // Generate a per-request nonce for inline scripts (Filament / Alpine / Livewire).
            // unsafe-eval is intentionally removed — Alpine.js v3 and Livewire 3 do not need it.
            $nonce = base64_encode(random_bytes(16));
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; "
                    ."script-src 'self' 'nonce-{$nonce}' 'unsafe-inline'; "
                    ."style-src 'self' 'unsafe-inline'; "
                    ."img-src 'self' data: https:; "
                    ."font-src 'self' data:; "
                    ."connect-src 'self' https:; "
                    ."frame-ancestors 'none'",
            );
            // Share nonce with views so Blade templates can use it on inline scripts
            view()->share('cspNonce', $nonce);
        }

        // Disable unused browser features
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(self), payment=()',
        );

        return $response;
    }
}
