<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

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
        
        if ($isOwnerArea) {
            // Owner-scoped session configuration
            Config::set('session.cookie', $this->getOwnerSessionCookieName());
            Config::set('session.path', '/owner');
            Config::set('session.same_site', 'lax');
        } else {
            // Customer-scoped session (default)
            Config::set('session.cookie', $this->getCustomerSessionCookieName());
            Config::set('session.path', '/');
            Config::set('session.same_site', 'lax');
        }
        
        return $next($request);
    }
    
    /**
     * Get owner-specific session cookie name.
     */
    private function getOwnerSessionCookieName(): string
    {
        $appName = env('APP_NAME', 'keyhome');
        $snakeName = \Illuminate\Support\Str::snake($appName);
        return "{$snakeName}_owner_session";
    }
    
    /**
     * Get customer-specific session cookie name (default).
     */
    private function getCustomerSessionCookieName(): string
    {
        return env('SESSION_COOKIE', \Illuminate\Support\Str::snake(env('APP_NAME', 'laravel')).'_session');
    }
}
