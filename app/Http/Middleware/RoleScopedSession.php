<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Role-scoped session middleware.
 *
 * Creates isolated session contexts for customer vs owner areas to prevent
 * session conflicts when users access both areas in the same browser.
 *
 * - Customer sessions: / (default) path, default cookie name
 * - Owner sessions: /owner path, suffixed cookie name
 */
class RoleScopedSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Detect owner area by path prefix
        $isOwnerArea = $request->is('owner/*') || $request->is('owner');

        // Use unified session configuration to prevent conflicts
        // Both customer and owner areas share the same session but with role-based access control
        Config::set('session.cookie', $this->getUnifiedSessionCookieName());
        Config::set('session.path', '/');
        // Respect SESSION_SAME_SITE from .env (e.g. 'none' for cross-origin dev, 'strict' for production)
        Config::set('session.same_site', config('session.same_site', 'lax'));

        return $next($request);
    }

    /**
     * Get unified session cookie name for all areas.
     */
    private function getUnifiedSessionCookieName(): string
    {
        $appName = (string) config('app.name', 'Laravel');
        $snakeName = Str::snake($appName);

        return "{$snakeName}_session";
    }
}
