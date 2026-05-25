<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Support\OpenRouteServiceAuth;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Queries OpenStreetMap Overpass API to compute a neighborhood scorecard.
 * Enriches each category with the nearest named POI and its real walking
 * distance via OpenRouteService (ORS). Falls back to orthodromic (haversine)
 * distance when ORS is unavailable or ORS_API_KEY is not set.
 *
 * Cache strategy:
 *   Legacy v1 entry (no nearest_poi)  → invalidated, re-computed
 *   Overpass OK                        → cached 7 days
 *   Overpass failure                   → cached 1 hour (retry soon)
 */
final readonly class NeighborhoodScorecardService
{
    private const int    CACHE_TTL = 604_800; // 7 days

    private const int    CACHE_TTL_FAIL = 3_600;   // 1 hour on Overpass failure

    private const string OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    private const string ORS_URL = 'https://api.openrouteservice.org/v2/matrix/foot-walking';

    private const int    RADIUS_NEAR = 500;

    private const int    RADIUS_FAR = 1_000;

    /** @var array<string, array{label: string, thresholds: array<int, int>, radius_m: int}> */
    private const array CATEGORY_CONFIG = [
        'transport' => ['label' => 'Transport',       'thresholds' => [0 => 0,  1 => 30, 2 => 55, 3 => 75, 5 => 90, 8 => 100], 'radius_m' => self::RADIUS_NEAR],
        'commerce' => ['label' => 'Commerces',       'thresholds' => [0 => 0,  1 => 35, 2 => 60, 4 => 80, 7 => 100],           'radius_m' => self::RADIUS_NEAR],
        'sante' => ['label' => 'Santé',           'thresholds' => [0 => 10, 1 => 60, 2 => 80, 4 => 100],                    'radius_m' => self::RADIUS_FAR],
        'education' => ['label' => 'Éducation',       'thresholds' => [0 => 0,  1 => 55, 2 => 80, 3 => 100],                    'radius_m' => self::RADIUS_FAR],
        'securite' => ['label' => 'Sécurité',        'thresholds' => [0 => 25, 1 => 70, 2 => 100],                             'radius_m' => self::RADIUS_FAR],
        'vie_sociale' => ['label' => 'Vie de quartier', 'thresholds' => [0 => 0,  1 => 20, 3 => 50, 6 => 75, 10 => 100],          'radius_m' => self::RADIUS_NEAR],
    ];

    public function __construct(private HttpFactory $http) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * @return array{
     *   global_score: int,
     *   status: 'ok'|'degraded'|'unavailable',
     *   cached: bool,
     *   computed_at: string|null,
     *   categories: array<string, array{
     *     score: int, poi_count: int, label: string, radius_m: int,
     *     nearest_poi: array{osm_id: string, name: string|null, distance_m: int, mode: 'walking'|'air'}|null
     *   }>
     * }
     */
    public function compute(float $lat, float $lng, bool $force = false): array
    {
        $latGrid = round($lat, 3);
        $lngGrid = round($lng, 3);
        $cacheKey = "neighborhood_scorecard_{$latGrid}_{$lngGrid}";
        $apiKey = (string) config('services.ors.key', '');

        $cached = Cache::get($cacheKey);

        // Hard force-refresh: drop the cache immediately and re-compute.
        if ($force && $cached !== null) {
            Cache::forget($cacheKey);
            $cached = null;
        }

        // Invalidate legacy v1 entries (categories have no nearest_poi key)
        if ($cached !== null && !$this->isV2Format($cached)) {
            Cache::forget($cacheKey);
            $cached = null;
        }

        // Invalidate v2 entries that were cached WITHOUT ORS distances when
        // the ORS key has since been configured — force a fresh computation
        // so walking distances replace the previous haversine (air) values.
        if ($cached !== null && $apiKey !== '' && !($cached['ors_used'] ?? false)) {
            Cache::forget($cacheKey);
            $cached = null;
        }

        if ($cached !== null) {
            return array_merge($cached, ['cached' => true]);
        }

        ['payload' => $payload, 'ttl' => $ttl] = $this->fetchAndScore($lat, $lng);
        Cache::put($cacheKey, $payload, $ttl);

        return array_merge($payload, ['cached' => false]);
    }

    // ─── Core pipeline ────────────────────────────────────────────────────────

    /** @return array{payload: array<string, mixed>, ttl: int} */
    private function fetchAndScore(float $lat, float $lng): array
    {
        $rawPois = $this->fetchPois($lat, $lng);
        $overpassFailed = ($rawPois === null);
        $elements = $rawPois ?? [];

        $nearest = $this->findNearestPois($elements, $lat, $lng);
        [$distances, $distStatus] = $this->fetchDistances($lat, $lng, $nearest);
        $categories = $this->buildCategories($nearest, $distances);

        $status = $overpassFailed ? 'unavailable' : $distStatus;

        $apiKey = (string) config('services.ors.key', '');

        return [
            'payload' => [
                'global_score' => $this->globalScore($categories),
                'status' => $status,
                'categories' => $categories,
                'computed_at' => now()->toIso8601String(),
                'ors_used' => $apiKey !== '',
            ],
            'ttl' => $overpassFailed ? self::CACHE_TTL_FAIL : self::CACHE_TTL,
        ];
    }

    // ─── Overpass ─────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>>|null  null = request failure */
    private function fetchPois(float $lat, float $lng): ?array
    {
        try {
            $response = $this->http
                ->timeout(15)
                ->retry(2, 1_500)
                ->asForm()
                ->post(self::OVERPASS_URL, ['data' => $this->overpassQuery($lat, $lng)]);

            if (!$response->successful()) {
                Log::warning('NeighborhoodScorecard: Overpass non-200', [
                    'status' => $response->status(), 'lat' => $lat, 'lng' => $lng,
                ]);

                return null;
            }

            return $response->json('elements', []);
        } catch (\Throwable $e) {
            Log::warning('NeighborhoodScorecard: Overpass request failed', [
                'error' => $e->getMessage(), 'lat' => $lat, 'lng' => $lng,
            ]);

            return null;
        }
    }

    private function overpassQuery(float $lat, float $lng): string
    {
        $n = self::RADIUS_NEAR;
        $f = self::RADIUS_FAR;

        return <<<OSM
[out:json][timeout:15];
(
  nwr["highway"="bus_stop"](around:{$n},{$lat},{$lng});
  nwr["amenity"~"^(taxi|bus_station)$"](around:{$n},{$lat},{$lng});
  nwr["public_transport"~"^(stop_position|platform|station)$"](around:{$n},{$lat},{$lng});
  nwr["amenity"="marketplace"](around:{$n},{$lat},{$lng});
  nwr["shop"~"^(supermarket|convenience|mall|grocery|general|variety_store|wholesale|department_store)$"](around:{$n},{$lat},{$lng});
  nwr["amenity"~"^(hospital|clinic|pharmacy|health_centre|doctors)$"](around:{$f},{$lat},{$lng});
  nwr["amenity"~"^(school|university|college|kindergarten)$"](around:{$f},{$lat},{$lng});
  nwr["amenity"~"^(police|fire_station)$"](around:{$f},{$lat},{$lng});
  nwr["amenity"~"^(restaurant|bank|atm|cafe|fast_food|place_of_worship|bar)$"](around:{$n},{$lat},{$lng});
);
out center;
OSM;
    }

    // ─── Nearest POI per category ─────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $pois
     * @return array<string, array{count: int, osm_id: string|null, name: string|null, lat: float|null, lng: float|null}>
     */
    private function findNearestPois(array $pois, float $adLat, float $adLng): array
    {
        /** @var array<string, array{count: int, osm_id: string|null, name: string|null, lat: float|null, lng: float|null, _dist: float}> $acc */
        $acc = [];

        foreach ($pois as $poi) {
            $tags = $poi['tags'] ?? [];
            $category = $this->resolveCategory($tags);

            if ($category === null) {
                continue;
            }

            $acc[$category]['count'] = ($acc[$category]['count'] ?? 0) + 1;

            // Nodes have lat/lon directly; ways/relations have center.lat/center.lon
            $poiLat = isset($poi['lat']) ? (float) $poi['lat'] : (isset($poi['center']['lat']) ? (float) $poi['center']['lat'] : null);
            $poiLng = isset($poi['lon']) ? (float) $poi['lon'] : (isset($poi['center']['lon']) ? (float) $poi['center']['lon'] : null);

            if ($poiLat === null || $poiLng === null) {
                continue;
            }

            $dist = $this->haversine($adLat, $adLng, $poiLat, $poiLng);

            if (!isset($acc[$category]['_dist']) || $dist < $acc[$category]['_dist']) {
                $acc[$category] = array_merge($acc[$category], [
                    'osm_id' => (string) ($poi['id'] ?? ''),
                    'name' => isset($tags['name']) ? (string) $tags['name'] : null,
                    'lat' => $poiLat,
                    'lng' => $poiLng,
                    '_dist' => $dist,
                ]);
            }
        }

        // Normalise — all 6 keys present, _dist stripped
        $result = [];
        foreach (array_keys(self::CATEGORY_CONFIG) as $cat) {
            $e = $acc[$cat] ?? [];
            $result[$cat] = [
                'count' => $e['count'] ?? 0,
                'osm_id' => $e['osm_id'] ?? null,
                'name' => $e['name'] ?? null,
                'lat' => $e['lat'] ?? null,
                'lng' => $e['lng'] ?? null,
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $tags */
    private function resolveCategory(array $tags): ?string
    {
        $amenity = (string) ($tags['amenity'] ?? '');
        $highway = (string) ($tags['highway'] ?? '');
        $shop = (string) ($tags['shop'] ?? '');
        $publicTransport = (string) ($tags['public_transport'] ?? '');

        if ($highway === 'bus_stop' || in_array($amenity, ['taxi', 'bus_station'], true) || in_array($publicTransport, ['stop_position', 'platform', 'station'], true)) {
            return 'transport';
        }
        if ($amenity === 'marketplace' || in_array($shop, ['supermarket', 'convenience', 'mall', 'grocery', 'general', 'variety_store', 'wholesale', 'department_store'], true)) {
            return 'commerce';
        }
        if (in_array($amenity, ['hospital', 'clinic', 'pharmacy', 'health_centre', 'doctors'], true)) {
            return 'sante';
        }
        if (in_array($amenity, ['school', 'university', 'college', 'kindergarten'], true)) {
            return 'education';
        }
        if (in_array($amenity, ['police', 'fire_station'], true)) {
            return 'securite';
        }
        if (in_array($amenity, ['restaurant', 'bank', 'atm', 'cafe', 'fast_food', 'place_of_worship', 'bar'], true)) {
            return 'vie_sociale';
        }

        return null;
    }

    // ─── ORS walking-distance matrix ─────────────────────────────────────────

    /**
     * Returns per-category distances and a pipeline status:
     *   'ok'       — haversine-by-design (no ORS key), or ORS succeeded
     *   'degraded' — ORS was configured but the call failed
     *
     * Individual nearest_poi.mode still tells 'walking'|'air' per category.
     *
     * @param  array<string, array{lat: float|null, lng: float|null}>  $nearest
     * @return array{0: array<string, array{distance_m: int, mode: 'walking'|'air'}>, 1: 'ok'|'degraded'}
     */
    private function fetchDistances(float $adLat, float $adLng, array $nearest): array
    {
        $apiKey = (string) config('services.ors.key', '');

        // No ORS key — haversine is the designed baseline, not a failure
        if ($apiKey === '') {
            return [$this->orthodromicDistances($adLat, $adLng, $nearest), 'ok'];
        }

        // Collect POIs that have coordinates — build [lng, lat] locations list (ORS order)
        $locations = [[$adLng, $adLat]];
        $orderedCats = [];

        foreach ($nearest as $cat => $poi) {
            if ($poi['lat'] !== null && $poi['lng'] !== null) {
                $locations[] = [$poi['lng'], $poi['lat']];
                $orderedCats[] = $cat;
            }
        }

        // No POI has coordinates — empty area, not a system failure
        if (count($locations) <= 1) {
            return [[], 'ok'];
        }

        try {
            $response = $this->http
                ->timeout(15)
                ->connectTimeout(5)
                ->withHeaders([
                    'Authorization' => OpenRouteServiceAuth::authorizationHeader($apiKey),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post(self::ORS_URL, [
                    'locations' => $locations,
                    'metrics' => ['distance'],
                    'sources' => [0],
                    'destinations' => range(1, count($locations) - 1),
                ]);

            if (!$response->successful()) {
                Log::info('NeighborhoodScorecard: ORS non-200, using haversine', ['status' => $response->status()]);

                return [$this->orthodromicDistances($adLat, $adLng, $nearest), 'degraded'];
            }

            // distances[0] is the single-source row; null = no pedestrian route for that POI
            $row = $response->json('distances.0', []);
            $distances = [];

            foreach ($orderedCats as $i => $cat) {
                $raw = $row[$i] ?? null;

                if ($raw !== null) {
                    $distances[$cat] = ['distance_m' => (int) round((float) $raw), 'mode' => 'walking'];
                } else {
                    // ORS returned null for this route — per-POI haversine fallback
                    $ortho = (int) round($this->haversine($adLat, $adLng, (float) $nearest[$cat]['lat'], (float) $nearest[$cat]['lng']));
                    $distances[$cat] = ['distance_m' => $ortho, 'mode' => 'air'];
                }
            }

            return [$distances, 'ok'];
        } catch (\Throwable $e) {
            Log::info('NeighborhoodScorecard: ORS failed, using haversine', ['error' => $e->getMessage()]);

            return [$this->orthodromicDistances($adLat, $adLng, $nearest), 'degraded'];
        }
    }

    /**
     * @param  array<string, array{lat: float|null, lng: float|null}>  $nearest
     * @return array<string, array{distance_m: int, mode: 'air'}>
     */
    private function orthodromicDistances(float $adLat, float $adLng, array $nearest): array
    {
        $result = [];
        foreach ($nearest as $cat => $poi) {
            if ($poi['lat'] !== null && $poi['lng'] !== null) {
                $result[$cat] = [
                    'distance_m' => (int) round($this->haversine($adLat, $adLng, $poi['lat'], $poi['lng'])),
                    'mode' => 'air',
                ];
            }
        }

        return $result;
    }

    // ─── Category scoring ─────────────────────────────────────────────────────

    /**
     * @param  array<string, array{count: int, osm_id: string|null, name: string|null, lat: float|null, lng: float|null}>  $nearest
     * @param  array<string, array{distance_m: int, mode: string}>  $distances
     * @return array<string, array{score: int, poi_count: int, label: string, radius_m: int, nearest_poi: array{osm_id: string, name: string|null, distance_m: int, mode: string}|null}>
     */
    private function buildCategories(array $nearest, array $distances): array
    {
        $result = [];

        foreach (self::CATEGORY_CONFIG as $key => $cfg) {
            $entry = $nearest[$key];
            $nearestPoi = null;

            if ($entry['lat'] !== null && isset($distances[$key])) {
                $nearestPoi = [
                    'osm_id' => (string) ($entry['osm_id'] ?? ''),
                    'name' => $entry['name'],
                    'distance_m' => $distances[$key]['distance_m'],
                    'mode' => $distances[$key]['mode'],
                ];
            }

            $result[$key] = [
                'score' => $this->stepScore($entry['count'], $cfg['thresholds']),
                'poi_count' => $entry['count'],
                'label' => $cfg['label'],
                'radius_m' => $cfg['radius_m'],
                'nearest_poi' => $nearestPoi,
            ];
        }

        return $result;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** @param array<int, int> $thresholds */
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

    /** @param array<string, array{score: int}> $categories */
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

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6_371_000.0;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlambda = deg2rad($lng2 - $lng1);
        $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlambda / 2) ** 2;

        return 2.0 * $r * asin(sqrt($a));
    }

    private function isV2Format(mixed $cached): bool
    {
        if (!is_array($cached)) {
            return false;
        }
        $first = array_values($cached['categories'] ?? [])[0] ?? null;

        return is_array($first) && array_key_exists('nearest_poi', $first);
    }
}
