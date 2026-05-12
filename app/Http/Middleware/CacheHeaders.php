<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds sensible Cache-Control headers to every API GET response.
 *
 * ┌────────────────────┬────────────────────────────────────────────────────────┐
 * │ Authenticated GET  │ private, no-store                                      │
 * │                    │ Browser: never cache. CDN: skips automatically when    │
 * │                    │ Authorization header is present.                       │
 * ├────────────────────┼────────────────────────────────────────────────────────┤
 * │ Public GET (guest) │ public, max-age=60, s-maxage=60,                       │
 * │ (default / dynamic)│ stale-while-revalidate=300, stale-if-error=3600        │
 * │                    │ CDN keeps for 60s, serves stale while revalidating     │
 * │                    │ for 5min; falls back to stale for 1h on origin errors. │
 * └────────────────────┴────────────────────────────────────────────────────────┘
 *
 * High-TTL reference data (cities, ad-types, etc.) is handled by the separate
 * CdnCache middleware which is applied per-route and overrides these defaults.
 *
 * Note: Cloudflare ignores responses with an Authorization request header by
 * default, so `Vary: Authorization` is unnecessary. We only vary on
 * Accept-Encoding (gzip vs. brotli) which affects the compressed body.
 */
final class CacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->isMethod('GET') || !$response instanceof JsonResponse) {
            return $response;
        }

        if (!$response->isSuccessful()) {
            return $response;
        }

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        } else {
            // Don't overwrite if CdnCache already set a longer TTL for this route.
            if (!$response->headers->has('Cache-Control')) {
                $response->headers->set(
                    'Cache-Control',
                    'public, max-age=60, s-maxage=60, stale-while-revalidate=300, stale-if-error=3600',
                );
            }
        }

        // Vary only on encoding — we always return JSON so Accept variation is wasteful.
        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }
}
