<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumStateful;

/**
 * Extends Sanctum's stateful middleware to respect the application's
 * SESSION_SAME_SITE setting instead of hard-coding 'lax'.
 */
class EnsureFrontendRequestsAreStateful extends SanctumStateful
{
    #[\Override]
    protected function configureSecureCookieSessions(): void
    {
        config([
            'session.http_only' => true,
            'session.same_site' => config('session.same_site', 'lax'),
        ]);
    }
}
