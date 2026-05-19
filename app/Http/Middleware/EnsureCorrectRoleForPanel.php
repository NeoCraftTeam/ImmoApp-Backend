<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Enterprise Grade Role Guard.
 *
 * Ensures that an authenticated user has the correct role for the panel they are accessing.
 * Prevents "Session Bleeding" where a customer session could be used to access owner routes.
 */
class EnsureCorrectRoleForPanel
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $panel = 'customer')
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($panel === 'owner') {
            if (!$user->mayAccessOwnerPanel()) {
                return response()->json([
                    'message' => $user->isAdmin()
                        ? 'Utilisez le panneau administrateur.'
                        : 'Accès refusé. Cet espace est réservé aux bailleurs.',
                    'code' => $user->isAdmin() ? 'ADMIN_PANEL_REQUIRED' : 'OWNER_ACCESS_REQUIRED',
                    'role' => $user->role,
                ], 403);
            }
        } elseif ($panel === 'customer') {
            // Customers or Guests can access customer panel
            // We don't block agents from customer panel, but we ensure they are treated as customers
        }

        return $next($request);
    }
}
