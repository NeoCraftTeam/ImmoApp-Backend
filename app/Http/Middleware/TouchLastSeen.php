<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Updates `users.last_seen_at` once per minute per authenticated user.
 *
 * Why a middleware (not a Pusher webhook):
 * - We don't depend on Pusher webhook delivery (extra config, occasional drops).
 * - Any authenticated API request is treated as activity — covers chat,
 *   browsing ads, opening the dashboard, etc.
 *
 * Why throttled via Cache:
 * - Without throttling, every API request would trigger a write on `users`
 *   (~1 write per page navigation). The 60-second cache key reduces this
 *   to at most one write per user per minute, which is plenty granular for
 *   "Vu il y a X" timestamps.
 *
 * Why a raw DB::update (not $user->update):
 * - Avoids triggering Eloquent observers / events / `updated_at` mutation.
 * - The column is purely a side-effect heartbeat; we don't want it to fire
 *   `UserObserver`, invalidate trust scores, etc.
 */
final class TouchLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user === null) {
            return $response;
        }

        $cacheKey = 'last_seen_touch:'.$user->id;
        if (Cache::has($cacheKey)) {
            return $response;
        }

        Cache::put($cacheKey, 1, now()->addMinute());

        // Fire-and-forget — never let a heartbeat write break a real request.
        try {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['last_seen_at' => now()]);
        } catch (\Throwable) {
            // Swallow: heartbeat write failures must not break the API response.
        }

        return $response;
    }
}
