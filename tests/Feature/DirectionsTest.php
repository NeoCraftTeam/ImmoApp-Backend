<?php

declare(strict_types=1);

use App\Services\Geo\DirectionsService;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fakeDirOrsOk(float $distanceM = 3_200.0, float $durationS = 480.0): ClientResponse
{
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'geometry' => ['type' => 'LineString', 'coordinates' => [[9.7679, 4.0511], [9.7720, 4.0550]]],
            'properties' => [
                'summary' => ['distance' => $distanceM, 'duration' => $durationS],
            ],
        ]],
    ];

    return new ClientResponse(
        new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode($geojson))
    );
}

function fakeDirHttpFactory(array $urlMap): HttpFactory
{
    $factory = new class($urlMap) extends HttpFactory
    {
        public function __construct(private readonly array $urlMap) {}

        public function connectTimeout(int $s): static
        {
            return $this;
        }

        public function timeout(int $s): static
        {
            return $this;
        }

        public function withHeaders(array $h): static
        {
            return $this;
        }

        public function post(string $url, array $data = []): ClientResponse
        {
            foreach ($this->urlMap as $pattern => $response) {
                if (str_contains($url, (string) $pattern)) {
                    return $response;
                }
            }

            return new ClientResponse(new Psr7Response(404));
        }
    };

    return $factory;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns 503 when ORS_API_KEY is not configured', function (): void {
    config(['services.ors.key' => null]);

    $this->getJson('/api/v1/directions?from_lat=4.05&from_lng=9.76&to_lat=4.06&to_lng=9.77')
        ->assertStatus(503);
});

it('returns 422 when required parameters are missing', function (): void {
    config(['services.ors.key' => 'test-key']);

    $this->getJson('/api/v1/directions?from_lat=4.05&from_lng=9.76')
        ->assertUnprocessable();
});

it('returns 422 for invalid profile', function (): void {
    config(['services.ors.key' => 'test-key']);

    $this->getJson('/api/v1/directions?from_lat=4.05&from_lng=9.76&to_lat=4.06&to_lng=9.77&profile=rocket')
        ->assertUnprocessable();
});

it('returns route GeoJSON and summary when ORS responds', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new DirectionsService(
        fakeDirHttpFactory(['openrouteservice.org' => fakeDirOrsOk(3_200.0, 480.0)])
    );
    app()->instance(DirectionsService::class, $service);

    $response = $this->getJson(
        '/api/v1/directions?from_lat=4.0511&from_lng=9.7679&to_lat=4.0550&to_lng=9.7720&profile=driving-car'
    );

    $response->assertOk()
        ->assertJsonPath('data.profile', 'driving-car')
        ->assertJsonPath('data.profile_label', 'En voiture')
        ->assertJsonPath('data.summary.distance_m', 3200)
        ->assertJsonPath('data.summary.duration_s', 480)
        ->assertJsonPath('data.summary.duration_label', '8 min')
        ->assertJsonPath('data.summary.distance_label', '3,2 km')
        ->assertJsonPath('data.cached', false)
        ->assertJsonStructure(['data' => ['geojson' => ['type', 'features']]]);
});

it('defaults to profile=driving-car when omitted', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new DirectionsService(
        fakeDirHttpFactory(['openrouteservice.org' => fakeDirOrsOk()])
    );
    app()->instance(DirectionsService::class, $service);

    $this->getJson('/api/v1/directions?from_lat=4.05&from_lng=9.76&to_lat=4.06&to_lng=9.77')
        ->assertOk()
        ->assertJsonPath('data.profile', 'driving-car');
});

it('formats walking duration correctly', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new DirectionsService(
        fakeDirHttpFactory(['openrouteservice.org' => fakeDirOrsOk(950.0, 685.0)])
    );
    app()->instance(DirectionsService::class, $service);

    $this->getJson('/api/v1/directions?from_lat=4.05&from_lng=9.76&to_lat=4.06&to_lng=9.77&profile=foot-walking')
        ->assertOk()
        ->assertJsonPath('data.summary.duration_label', '11 min')
        ->assertJsonPath('data.summary.distance_label', '950 m');
});

it('formats multi-hour duration correctly', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new DirectionsService(
        fakeDirHttpFactory(['openrouteservice.org' => fakeDirOrsOk(120_000.0, 5_400.0)])
    );
    app()->instance(DirectionsService::class, $service);

    $this->getJson('/api/v1/directions?from_lat=4.05&from_lng=9.76&to_lat=4.06&to_lng=9.77')
        ->assertOk()
        ->assertJsonPath('data.summary.duration_label', '1h30');
});

it('serves a cached route without re-calling ORS', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $cacheKey = 'directions_driving-car_4.051_9.768_4.055_9.772';
    Cache::put($cacheKey, [
        'geojson' => ['type' => 'FeatureCollection', 'features' => []],
        'summary' => ['distance_m' => 1000, 'duration_s' => 120, 'distance_label' => '1 km', 'duration_label' => '2 min'],
        'profile' => 'driving-car',
        'profile_label' => 'En voiture',
        'cached' => false,
    ], 3600);

    $service = new DirectionsService(fakeDirHttpFactory([]));
    app()->instance(DirectionsService::class, $service);

    $this->getJson('/api/v1/directions?from_lat=4.0511&from_lng=9.7679&to_lat=4.0550&to_lng=9.7720')
        ->assertOk()
        ->assertJsonPath('data.cached', true);
});

it('returns 503 when ORS returns a non-200 status', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new DirectionsService(
        fakeDirHttpFactory(['openrouteservice.org' => new ClientResponse(new Psr7Response(429))])
    );
    app()->instance(DirectionsService::class, $service);

    $this->getJson('/api/v1/directions?from_lat=4.05&from_lng=9.76&to_lat=4.06&to_lng=9.77')
        ->assertStatus(503);
});

// Gap 7: prevent an unauth'd client from draining the ORS free tier
// (2000 calls/day) from a single IP. 30 hits/min/IP matches the
// sibling /isochrones route.
it('throttles directions at 30 requests per minute per IP', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    // Serve cheap cached responses — the limit kicks in regardless.
    // Cache key matches DirectionsService::get format (3-dp rounding).
    Cache::put(
        'directions_driving-car_4.050_9.760_4.060_9.770',
        [
            'geojson' => ['type' => 'FeatureCollection', 'features' => []],
            'summary' => ['distance_m' => 1000, 'duration_s' => 120, 'distance_label' => '1 km', 'duration_label' => '2 min'],
            'profile' => 'driving-car',
            'profile_label' => 'En voiture',
            'cached' => false,
        ],
        3600
    );

    // Fresh limiter slot — the test runner shares the RateLimiter cache,
    // so isolate by clearing the key Laravel uses for the throttle.
    RateLimiter::clear(sha1('127.0.0.1'));

    $url = '/api/v1/directions?from_lat=4.05&from_lng=9.76&to_lat=4.06&to_lng=9.77';

    for ($i = 0; $i < 30; $i++) {
        $this->getJson($url)->assertOk();
    }

    // 31st call from the same IP within the window returns 429.
    $this->getJson($url)->assertStatus(429);
});
