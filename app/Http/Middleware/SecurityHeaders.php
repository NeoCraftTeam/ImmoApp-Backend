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
        $isApiRoute = $request->is('api/*') && !$request->routeIs('ads.pdf');
        $isDocsRoute = $request->is('api/documentation') || $request->is('docs/*') || $request->is('api/oauth2-callback');

        // Filament panels may run on configured custom domains (FILAMENT_ADMIN_DOMAIN /
        // FILAMENT_AGENCY_DOMAIN), on convention-based subdomains (admin.*, agency.*),
        // or on path prefixes (/admin, /agency). Check configured domains first so that
        // arbitrary subdomains like panel.keyhome.neocraft.dev are correctly identified.
        $host = $request->getHost();
        $filamentDomains = array_filter([
            config('filament.panels.admin_domain'),
            config('filament.panels.agency_domain'),
            config('filament.panels.owner_domain'),
        ]);
        $isFilamentPanel = in_array($host, $filamentDomains, true)
            || str_starts_with($host, 'admin.')
            || str_starts_with($host, 'agency.')
            || $request->is('admin') || $request->is('admin/*')
            || $request->is('agency') || $request->is('agency/*');

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
        } elseif ($isFilamentPanel) {
            // Filament panels (Livewire 3 + Alpine.js) are incompatible with nonce-based CSP:
            // (a) a nonce in script-src silently invalidates unsafe-inline per the CSP spec,
            //     blocking every Livewire snapshot / Alpine x-data inline script that lacks it;
            // (b) Alpine.js standard build (used by Filament 4) calls new Function() which
            //     requires unsafe-eval — the previous comment claiming otherwise was wrong.
            // Nonce-based CSP would require patching Livewire + Alpine source to inject the
            // nonce into every emitted script tag — not feasible. Use unsafe-inline + unsafe-eval.
            // fonts.bunny.net is required by Filament's ->font('poppins') configuration.
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; "
                    ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
                    ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
                    ."img-src 'self' data: blob: https:; "
                    ."font-src 'self' data: https://fonts.bunny.net; "
                    ."connect-src 'self' https: wss:; "
                    ."frame-ancestors 'none'",
            );
        } else {
            // Other web routes: nonce-based policy (no Livewire/Alpine inline scripts here).
            $nonce = base64_encode(random_bytes(16));
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; "
                    ."script-src 'self' 'nonce-{$nonce}'; "
                    ."style-src 'self' 'unsafe-inline'; "
                    ."img-src 'self' data: https:; "
                    ."font-src 'self' data:; "
                    ."connect-src 'self' https:; "
                    ."frame-ancestors 'none'",
            );
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
