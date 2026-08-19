<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Support\OpenRouteServiceAuth;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class IsochroneService
{
    private const string ORS_URL = 'https://api.openrouteservice.org/v2/isochrones';

    private const int CACHE_TTL = 86_400;   // 24 h — isochrones are stable

    private const int CACHE_TTL_FAIL = 300;   // 5 min on ORS error

    /** Supported ORS foot/vehicle profiles */
    public const array PROFILES = [
        'foot-walking',
        'driving-car',
        'cycling-regular',
        'wheelchair',
    ];

    public function __construct(private HttpFactory $http) {}

    /**
     * Return an isochrone GeoJSON FeatureCollection for the given point.
     *
     * @return array{geojson: array<string, mixed>, profile: string, range_minutes: int, center: array{lat: float, lng: float}, cached: bool}|null
     *                                                                                                                                             Null when ORS is not configured.
     */
    public function get(float $lat, float $lng, string $profile, int $rangeMinutes): ?array
    {
        $apiKey = (string) config('services.ors.key', '');

        if ($apiKey === '') {
            return null;
        }

        // `number_format` with an explicit decimal point is locale-independent,
        // unlike `sprintf('%.3f')` which honours `LC_NUMERIC` and would emit a
        // comma (e.g. `4,051`) under a fr_* runtime locale — silently shifting
        // every cache key and defeating the cache on such hosts.
        $cacheKey = sprintf(
            'isochrone_%s_%s_%s_%d',
            $profile,
            number_format(round($lat, 3), 3, '.', ''),
            number_format(round($lng, 3), 3, '.', ''),
            $rangeMinutes,
        );

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $cached['cached'] = true;

            return $cached;
        }

        // Honour the short error-cooldown so a broken ORS key won't get hammered
        if (Cache::has($cacheKey.'_miss')) {
            return null;
        }

        $payload = $this->compute($lat, $lng, $profile, $rangeMinutes, $apiKey);

        if ($payload !== null) {
            Cache::put($cacheKey, $payload, self::CACHE_TTL);
        } else {
            // Short cache to avoid hammering ORS on transient errors
            Cache::put($cacheKey.'_miss', true, self::CACHE_TTL_FAIL);
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function compute(float $lat, float $lng, string $profile, int $rangeMinutes, string $apiKey): ?array
    {
        try {
            $response = $this->http
                ->timeout(10)
                ->withHeaders([
                    'Authorization' => OpenRouteServiceAuth::authorizationHeader($apiKey),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post(self::ORS_URL.'/'.$profile, [
                    'locations' => [[$lng, $lat]],
                    'range' => [$rangeMinutes * 60],
                    'range_type' => 'time',
                    'attributes' => ['area'],
                    'units' => 'm',
                ]);

            if (!$response->successful()) {
                Log::info('IsochroneService: ORS error', ['status' => $response->status()]);

                return null;
            }

            return [
                'geojson' => $response->json(),
                'profile' => $profile,
                'range_minutes' => $rangeMinutes,
                'center' => ['lat' => $lat, 'lng' => $lng],
                'cached' => false,
            ];
        } catch (\Throwable $e) {
            Log::info('IsochroneService: request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
