<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user from a Bearer Sanctum token on web routes.
 * Used by /tour-image so Next.js (or mobile) can proxy images with Authorization
 * while guests still access signed URLs.
 */
final class ResolveSanctumBearerUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken();
        if ($raw === null || $raw === '') {
            return $next($request);
        }

        $accessToken = PersonalAccessToken::findToken($raw);

        if ($accessToken && $accessToken->tokenable instanceof Authenticatable) {
            Auth::guard('sanctum')->setUser($accessToken->tokenable);
            $request->setUserResolver(fn () => Auth::guard('sanctum')->user());
        }

        return $next($request);
    }
}
