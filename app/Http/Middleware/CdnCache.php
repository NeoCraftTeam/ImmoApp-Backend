<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets long-lived Cache-Control headers on public reference-data routes.
 *
 * Usage in routes:  ->middleware('cdn.cache:3600')
 *
 * This sets headers BEFORE the request is processed (via a pre-hook that marks
 * the route), and overwrites Cache-Control on the response.
 *
 * Only applies to anonymous GET requests that return a successful JSON response.
 * Authenticated requests always get private, no-store regardless of this middleware.
 *
 * Strategy (Cloudflare edge):
 *   - s-maxage      : how long Cloudflare caches the response (seconds)
 *   - max-age       : how long the browser caches (set equal to s-maxage)
 *   - swr           : stale-while-revalidate — serve stale while re-fetching in bg
 *   - sie           : stale-if-error — serve stale if origin is down (7 days)
 *
 * Reference data TTL guidelines:
 *   - cities / quarters / ad-types    : 3600  (1 hour)
 *   - property-attributes             : 1800  (30 min)
 *   - subscription-plans / packages   : 1800  (30 min)
 *   - stats/landing                   : 300   (5 min)
 *   - stats/testimonials              : 3600  (1 hour)
 */
final class CdnCache
{
    public function handle(Request $request, Closure $next, int $ttl = 3600): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->isMethod('GET') || !$response instanceof JsonResponse) {
            return $response;
        }

        if (!$response->isSuccessful()) {
            return $response;
        }

        // Never cache personalized/authenticated responses — the downstream
        // CacheHeaders middleware will set private, no-store in this case.
        if ($request->user()) {
            return $response;
        }

        $swr = min($ttl * 24, 86400);     // stale-while-revalidate: up to 24h
        $sie = 604800;                     // stale-if-error: 7 days (origin outage resilience)

        $response->headers->set(
            'Cache-Control',
            "public, max-age={$ttl}, s-maxage={$ttl}, stale-while-revalidate={$swr}, stale-if-error={$sie}",
        );

        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
