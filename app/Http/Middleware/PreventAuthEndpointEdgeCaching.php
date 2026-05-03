<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces no CDN / browser caching on auth API routes so Cloudflare never
 * serves stale login, magic-link, or token responses.
 */
final class PreventAuthEndpointEdgeCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->is('api/v1/auth*')) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('CDN-Cache-Control', 'private, no-store');

        return $response;
    }
}
