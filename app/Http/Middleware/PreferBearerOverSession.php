<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a Bearer token authoritative over the stateful session cookie.
 *
 * Sanctum resolves `config('sanctum.guard')` (the `web` session) BEFORE the
 * Bearer token. In a multi-panel SPA where a single browser can hold both an
 * owner and a client login, the shared session cookie holds only the
 * last-authenticated user — so a client-context request carrying a valid
 * client Bearer would still resolve to the owner session, leaking the wrong
 * profile across panels.
 *
 * When a request carries a Bearer token we clear the Sanctum session guards
 * FOR THIS REQUEST ONLY, forcing Sanctum straight to its own (robust) token
 * resolution — expiry, abilities, `last_used_at`, `withAccessToken()` all
 * intact, so `currentAccessToken()`, `token.role` and logout revocation keep
 * working. Requests without a Bearer (e.g. the post-reload bootstrap, where
 * in-memory tokens are gone) still fall back to the session cookie, preserving
 * "stay logged in across reloads".
 *
 * Prepended to the `api` group so it runs before `auth:sanctum` resolves the
 * user. The `web`-group routes that rely on `auth:web,…` are untouched.
 */
final class PreferBearerOverSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer !== null && $bearer !== '') {
            Config::set('sanctum.guard', []);
        }

        return $next($request);
    }
}
