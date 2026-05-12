<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that gates routes behind a feature flag.
 *
 * Usage: Route::get('/foo', ...)->middleware('feature:natural_search');
 */
class CheckFeatureFlag
{
    public function __construct(private readonly FeatureFlagService $features) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!$this->features->isEnabled($feature)) {
            abort(404, 'This feature is currently unavailable.');
        }

        return $next($request);
    }
}
