<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to the local development environment only.
 * Use on Swagger UI routes so the docs are never exposed in production.
 */
final class LocalEnvironmentOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            app()->isLocal(),
            403,
            'Documentation API disponible uniquement en environnement local (APP_ENV=local).'
        );

        return $next($request);
    }
}
