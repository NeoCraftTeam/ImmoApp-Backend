<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds Cache-Control headers to public API GET responses.
 * Authenticated responses get private caching; public responses get shared caching.
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
            $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');
        } else {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=120');
        }

        $response->headers->set('Vary', 'Accept, Authorization');

        return $response;
    }
}
