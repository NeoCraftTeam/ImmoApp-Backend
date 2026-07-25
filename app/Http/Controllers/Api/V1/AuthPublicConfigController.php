<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiResponse;
use App\Support\OAuthProviderAvailability;
use Illuminate\Http\JsonResponse;

/**
 * Public auth configuration for mobile / SPA clients.
 * Exposes which OAuth flows are available without leaking secrets.
 */
final class AuthPublicConfigController
{
    public function __invoke(): JsonResponse
    {
        $clerkEnabled = OAuthProviderAvailability::isClerkConfigured();
        $publishableKey = $clerkEnabled ? (string) config('clerk.publishable_key') : null;

        return ApiResponse::success('OK', [
            'clerk' => [
                'enabled' => $clerkEnabled,
                'publishable_key' => $publishableKey,
                /** OAuth via Clerk (web + mobile) — not Laravel Socialite redirect routes. */
                'oauth_providers' => $clerkEnabled ? ['google', 'facebook', 'github'] : [],
            ],
            'socialite' => OAuthProviderAvailability::socialiteMap(),
            'google' => [
                /** Prefer Clerk (same as web PWA) when Socialite Google is not configured. */
                'method' => OAuthProviderAvailability::isSocialiteConfigured('google')
                    ? 'socialite'
                    : ($clerkEnabled ? 'clerk' : 'unavailable'),
            ],
        ]);
    }
}
