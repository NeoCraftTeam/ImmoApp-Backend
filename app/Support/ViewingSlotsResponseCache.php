<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Ad;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the public JSON for GET /ads/{ad}/slots. Invalidation uses a per-ad generation
 * counter so it works with cache drivers that do not support tags (file, database, array).
 */
final class ViewingSlotsResponseCache
{
    public const int TTL_SECONDS = 60;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(Ad $ad, string $from, string $to, Closure $callback): mixed
    {
        $gen = (int) Cache::get(self::generationCacheKey($ad->id), 0);
        $key = "viewing_slots_response:{$ad->id}:g{$gen}:{$from}:{$to}";

        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }

    public static function bumpGeneration(Ad $ad): void
    {
        self::bumpGenerationForAdId($ad->id);
    }

    public static function bumpGenerationForAdId(string $adId): void
    {
        $k = self::generationCacheKey($adId);
        Cache::put($k, ((int) Cache::get($k, 0)) + 1, now()->addYears(10));
    }

    private static function generationCacheKey(string $adId): string
    {
        return "viewing_slots_response_gen:{$adId}";
    }
}
