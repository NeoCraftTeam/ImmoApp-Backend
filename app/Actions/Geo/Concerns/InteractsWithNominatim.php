<?php

declare(strict_types=1);

namespace App\Actions\Geo\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared Nominatim (OpenStreetMap) access enforcing the OSM usage policy:
 * ≤ 1 request/second, a valid User-Agent, and a mandatory result cache.
 *
 * Every lookup is cached — positive AND negative, so a miss (unknown place or a
 * transient failure) is not re-hammered — and serialized behind a global lock
 * that spaces outbound requests by `services.nominatim.min_interval_ms`. Callers
 * get a decoded results list and never see an exception: timeouts/network
 * errors degrade to an empty array.
 */
trait InteractsWithNominatim
{
    /**
     * @param  array<string, scalar>  $params  extra Nominatim query params (e.g. addressdetails)
     * @return array<int, array<string, mixed>>
     */
    protected function nominatimSearch(string $query, array $params = []): array
    {
        $normalized = mb_strtolower(trim($query));

        if ($normalized === '') {
            return [];
        }

        $cacheKey = 'geo:nominatim:'.md5($normalized.'|'.serialize($params));

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $results = $this->requestNominatim($query, $params);

        Cache::put(
            $cacheKey,
            $results,
            $results === []
                ? (int) config('services.nominatim.negative_ttl')
                : (int) config('services.nominatim.ttl'),
        );

        return $results;
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<int, array<string, mixed>>
     */
    private function requestNominatim(string $query, array $params): array
    {
        try {
            // Serialize concurrent callers so global throughput respects OSM's
            // 1 req/s policy even under parallel bailleur submissions.
            $lock = Cache::lock('geo:nominatim:request', 15);
            $lock->block(15);

            try {
                $this->spaceNominatimRequests();

                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => (string) config('services.nominatim.user_agent')])
                    ->get((string) config('services.nominatim.url'), array_merge(
                        ['format' => 'json', 'limit' => 1],
                        $params,
                        ['q' => $query],
                    ));

                return $response->ok() ? (array) $response->json() : [];
            } finally {
                $lock->release();
            }
        } catch (\Throwable $e) {
            Log::warning('Nominatim request failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Sleep just long enough that consecutive outbound requests are spaced by at
     * least `min_interval_ms`. No-op when the interval is 0 (tests).
     */
    private function spaceNominatimRequests(): void
    {
        $minIntervalMs = (int) config('services.nominatim.min_interval_ms');

        if ($minIntervalMs <= 0) {
            return;
        }

        $lastAt = Cache::get('geo:nominatim:last-request-at');

        if (is_float($lastAt) || is_int($lastAt)) {
            $waitMs = $minIntervalMs - ((microtime(true) - $lastAt) * 1000);

            if ($waitMs > 0) {
                usleep((int) ($waitMs * 1000));
            }
        }

        Cache::put('geo:nominatim:last-request-at', microtime(true), 60);
    }
}
