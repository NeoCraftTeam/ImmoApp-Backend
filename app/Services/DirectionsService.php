<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class DirectionsService
{
    private const string ORS_URL = 'https://api.openrouteservice.org/v2/directions';

    private const int CACHE_TTL = 3_600; // 1 h — routes are fairly stable

    /** Supported ORS profiles */
    public const array PROFILES = [
        'foot-walking',
        'driving-car',
        'cycling-regular',
        'wheelchair',
    ];

    /** Human-readable labels (fr) */
    public const array PROFILE_LABELS = [
        'foot-walking' => 'À pied',
        'driving-car' => 'En voiture',
        'cycling-regular' => 'À vélo',
        'wheelchair' => 'Fauteuil roulant',
    ];

    public function __construct(private HttpFactory $http) {}

    /**
     * Return a route from A → B.
     *
     * @return array{
     *   geojson: array<string, mixed>,
     *   summary: array{distance_m: int, duration_s: int, duration_label: string, distance_label: string},
     *   profile: string,
     *   profile_label: string,
     *   cached: bool
     * }|null  Null when ORS is not configured or route not found.
     */
    public function get(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        string $profile,
    ): ?array {
        $apiKey = (string) config('services.ors.key', '');

        if ($apiKey === '') {
            return null;
        }

        $cacheKey = sprintf(
            'directions_%s_%.3f_%.3f_%.3f_%.3f',
            $profile,
            round($fromLat, 3),
            round($fromLng, 3),
            round($toLat, 3),
            round($toLng, 3),
        );

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $cached['cached'] = true;

            return $cached;
        }

        $payload = $this->compute($fromLat, $fromLng, $toLat, $toLng, $profile, $apiKey);

        if ($payload !== null) {
            Cache::put($cacheKey, $payload, self::CACHE_TTL);
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function compute(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        string $profile,
        string $apiKey,
    ): ?array {
        try {
            $response = $this->http
                ->timeout(10)
                ->withHeaders(['Authorization' => $apiKey])
                ->post(self::ORS_URL.'/'.$profile.'/geojson', [
                    'coordinates' => [
                        [$fromLng, $fromLat],
                        [$toLng, $toLat],
                    ],
                    'instructions' => false,
                ]);

            if (!$response->successful()) {
                Log::info('DirectionsService: ORS error', ['status' => $response->status()]);

                return null;
            }

            $json = $response->json();
            $segment = $json['features'][0]['properties']['summary'] ?? null;

            if ($segment === null) {
                return null;
            }

            $distanceM = (int) round((float) ($segment['distance'] ?? 0));
            $durationS = (int) round((float) ($segment['duration'] ?? 0));

            return [
                'geojson' => $json,
                'summary' => [
                    'distance_m' => $distanceM,
                    'duration_s' => $durationS,
                    'distance_label' => $this->formatDistance($distanceM),
                    'duration_label' => $this->formatDuration($durationS),
                ],
                'profile' => $profile,
                'profile_label' => self::PROFILE_LABELS[$profile] ?? $profile,
                'cached' => false,
            ];
        } catch (\Throwable $e) {
            Log::info('DirectionsService: request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function formatDistance(int $meters): string
    {
        if ($meters >= 1000) {
            return number_format($meters / 1000, 1, ',', ' ').' km';
        }

        return $meters.' m';
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $hours.'h'.($minutes > 0 ? sprintf('%02d', $minutes) : '');
        }

        return max(1, $minutes).' min';
    }
}
