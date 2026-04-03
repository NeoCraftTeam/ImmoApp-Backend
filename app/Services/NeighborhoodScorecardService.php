<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Queries OpenStreetMap Overpass API to compute a neighborhood scorecard
 * for a given GPS coordinate.
 *
 * Results are cached in Redis for 7 days — POI data changes slowly.
 * Grid resolution is 3 decimal places (~110 m) so nearby ads share results.
 */
final readonly class NeighborhoodScorecardService
{
    private const int    CACHE_TTL = 604_800;               // 7 days

    private const string OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    private const int    RADIUS_NEAR = 500;                   // metres

    private const int    RADIUS_FAR = 1_000;                 // metres

    public function __construct(private HttpFactory $http) {}

    /**
     * @return array{
     *   global_score: int,
     *   categories: array<string, array{score: int, poi_count: int, label: string, radius_m: int}>,
     *   cached: bool,
     *   computed_at: string|null
     * }
     */
    public function compute(float $lat, float $lng): array
    {
        $latGrid = round($lat, 3);
        $lngGrid = round($lng, 3);
        $cacheKey = "neighborhood_scorecard_{$latGrid}_{$lngGrid}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return array_merge($cached, ['cached' => true]);
        }

        $result = $this->fetchAndScore($lat, $lng);
        Cache::put($cacheKey, $result, self::CACHE_TTL);

        return array_merge($result, ['cached' => false]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{global_score: int, categories: array<string, mixed>, computed_at: string} */
    private function fetchAndScore(float $lat, float $lng): array
    {
        $pois = $this->fetchPois($lat, $lng);
        $counts = $this->categorizePois($pois);
        $categories = $this->buildCategories($counts);

        return [
            'global_score' => $this->globalScore($categories),
            'categories' => $categories,
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchPois(float $lat, float $lng): array
    {
        try {
            $response = $this->http
                ->timeout(15)
                ->retry(2, 1_500)
                ->post(self::OVERPASS_URL, ['data' => $this->query($lat, $lng)]);

            if (!$response->successful()) {
                Log::warning('NeighborhoodScorecard: Overpass non-200', [
                    'status' => $response->status(),
                    'lat' => $lat,
                    'lng' => $lng,
                ]);

                return [];
            }

            return $response->json('elements', []);
        } catch (\Throwable $e) {
            Log::warning('NeighborhoodScorecard: Overpass request failed', [
                'error' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng,
            ]);

            return [];
        }
    }

    private function query(float $lat, float $lng): string
    {
        $n = self::RADIUS_NEAR;
        $f = self::RADIUS_FAR;

        return <<<OSM
[out:json][timeout:15];
(
  node["highway"="bus_stop"](around:{$n},{$lat},{$lng});
  node["amenity"~"^(taxi|bus_station)$"](around:{$n},{$lat},{$lng});
  node["amenity"="marketplace"](around:{$n},{$lat},{$lng});
  node["shop"~"^(supermarket|convenience|mall|grocery|general)$"](around:{$n},{$lat},{$lng});
  node["amenity"~"^(hospital|clinic|pharmacy|health_centre|doctors)$"](around:{$f},{$lat},{$lng});
  node["amenity"~"^(school|university|college|kindergarten)$"](around:{$f},{$lat},{$lng});
  node["amenity"~"^(police|fire_station)$"](around:{$f},{$lat},{$lng});
  node["amenity"~"^(restaurant|bank|atm|cafe|fast_food)$"](around:{$n},{$lat},{$lng});
);
out;
OSM;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pois
     * @return array<string, int>
     */
    private function categorizePois(array $pois): array
    {
        $counts = [
            'transport' => 0,
            'commerce' => 0,
            'sante' => 0,
            'education' => 0,
            'securite' => 0,
            'vie_sociale' => 0,
        ];

        foreach ($pois as $poi) {
            $tags = $poi['tags'] ?? [];
            $amenity = $tags['amenity'] ?? '';
            $highway = $tags['highway'] ?? '';
            $shop = $tags['shop'] ?? '';

            if ($highway === 'bus_stop' || in_array($amenity, ['taxi', 'bus_station'], true)) {
                $counts['transport']++;
            } elseif ($amenity === 'marketplace' || in_array($shop, ['supermarket', 'convenience', 'mall', 'grocery', 'general'], true)) {
                $counts['commerce']++;
            } elseif (in_array($amenity, ['hospital', 'clinic', 'pharmacy', 'health_centre', 'doctors'], true)) {
                $counts['sante']++;
            } elseif (in_array($amenity, ['school', 'university', 'college', 'kindergarten'], true)) {
                $counts['education']++;
            } elseif (in_array($amenity, ['police', 'fire_station'], true)) {
                $counts['securite']++;
            } elseif (in_array($amenity, ['restaurant', 'bank', 'atm', 'cafe', 'fast_food'], true)) {
                $counts['vie_sociale']++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, array{score: int, poi_count: int, label: string, radius_m: int}>
     */
    private function buildCategories(array $counts): array
    {
        $config = [
            'transport' => ['label' => 'Transport',       'thresholds' => [0 => 0,  1 => 30, 2 => 55, 3 => 75, 5 => 90, 8 => 100], 'radius_m' => self::RADIUS_NEAR],
            'commerce' => ['label' => 'Commerces',       'thresholds' => [0 => 0,  1 => 35, 2 => 60, 4 => 80, 7 => 100],           'radius_m' => self::RADIUS_NEAR],
            'sante' => ['label' => 'Santé',           'thresholds' => [0 => 10, 1 => 60, 2 => 80, 4 => 100],                    'radius_m' => self::RADIUS_FAR],
            'education' => ['label' => 'Éducation',       'thresholds' => [0 => 0,  1 => 55, 2 => 80, 3 => 100],                    'radius_m' => self::RADIUS_FAR],
            'securite' => ['label' => 'Sécurité',        'thresholds' => [0 => 25, 1 => 70, 2 => 100],                             'radius_m' => self::RADIUS_FAR],
            'vie_sociale' => ['label' => 'Vie de quartier', 'thresholds' => [0 => 0,  1 => 20, 3 => 50, 6 => 75, 10 => 100],          'radius_m' => self::RADIUS_NEAR],
        ];

        $result = [];
        foreach ($config as $key => $cfg) {
            $count = $counts[$key] ?? 0;
            $result[$key] = [
                'score' => $this->stepScore($count, $cfg['thresholds']),
                'poi_count' => $count,
                'label' => $cfg['label'],
                'radius_m' => $cfg['radius_m'],
            ];
        }

        return $result;
    }

    /**
     * Returns the highest score whose min_count threshold is satisfied.
     *
     * @param  array<int, int>  $thresholds  [min_count => score, ...]
     */
    private function stepScore(int $count, array $thresholds): int
    {
        $score = 0;
        foreach ($thresholds as $min => $s) {
            if ($count >= $min) {
                $score = $s;
            }
        }

        return $score;
    }

    /**
     * Weighted average — transport and commerce get the most weight
     * in West African urban context (motos + marchés are primary).
     *
     * @param  array<string, array{score: int}>  $categories
     */
    private function globalScore(array $categories): int
    {
        $weights = [
            'transport' => 0.25,
            'commerce' => 0.25,
            'sante' => 0.20,
            'education' => 0.15,
            'securite' => 0.05,
            'vie_sociale' => 0.10,
        ];

        $total = 0.0;
        foreach ($weights as $key => $w) {
            $total += ($categories[$key]['score'] ?? 0) * $w;
        }

        return (int) round($total);
    }
}
