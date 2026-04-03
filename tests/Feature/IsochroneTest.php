<?php

declare(strict_types=1);

use App\Services\IsochroneService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Psr7\Response as Psr7Response;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fakeIsochroneOrsOk(): ClientResponse
{
    $geojson = [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[9.76, 4.04], [9.77, 4.05], [9.76, 4.06], [9.76, 4.04]]]],
            'properties' => ['area' => 1_500_000.0],
        ]],
    ];

    return new ClientResponse(
        new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode($geojson))
    );
}

function fakeIsoHttpFactory(array $urlMap): HttpFactory
{
    $factory = new class ($urlMap) extends HttpFactory {
        public function __construct(private array $urlMap) {}

        public function timeout(int $s): static { return $this; }

        public function withHeaders(array $h): static { return $this; }

        public function post(string $url, array $data = []): ClientResponse
        {
            foreach ($this->urlMap as $pattern => $response) {
                if (str_contains($url, $pattern)) {
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

    $this->getJson('/api/v1/isochrones?lat=4.0511&lng=9.7679')
        ->assertStatus(503);
});

it('returns 422 for invalid coordinates', function (): void {
    config(['services.ors.key' => 'test-key']);

    $this->getJson('/api/v1/isochrones?lat=999&lng=9.7679')
        ->assertUnprocessable();
});

it('returns 422 for out-of-range range parameter', function (): void {
    config(['services.ors.key' => 'test-key']);

    $this->getJson('/api/v1/isochrones?lat=4.0511&lng=9.7679&range=120')
        ->assertUnprocessable();
});

it('returns 422 for invalid profile', function (): void {
    config(['services.ors.key' => 'test-key']);

    $this->getJson('/api/v1/isochrones?lat=4.0511&lng=9.7679&profile=jet-ski')
        ->assertUnprocessable();
});

it('returns isochrone GeoJSON when ORS is configured and responds', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new IsochroneService(
        fakeIsoHttpFactory(['openrouteservice.org' => fakeIsochroneOrsOk()])
    );
    app()->instance(IsochroneService::class, $service);

    $response = $this->getJson('/api/v1/isochrones?lat=4.0511&lng=9.7679&profile=foot-walking&range=15');

    $response->assertOk()
        ->assertJsonPath('data.profile', 'foot-walking')
        ->assertJsonPath('data.range_minutes', 15)
        ->assertJsonPath('data.center.lat', 4.0511)
        ->assertJsonPath('data.center.lng', 9.7679)
        ->assertJsonPath('data.cached', false)
        ->assertJsonStructure(['data' => ['geojson' => ['type', 'features']]]);
});

it('defaults to profile=foot-walking and range=15 when omitted', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new IsochroneService(
        fakeIsoHttpFactory(['openrouteservice.org' => fakeIsochroneOrsOk()])
    );
    app()->instance(IsochroneService::class, $service);

    $response = $this->getJson('/api/v1/isochrones?lat=4.0511&lng=9.7679');

    $response->assertOk()
        ->assertJsonPath('data.profile', 'foot-walking')
        ->assertJsonPath('data.range_minutes', 15);
});

it('serves a cached isochrone without re-calling ORS', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $cacheKey = 'isochrone_foot-walking_4.051_9.768_15';
    Cache::put($cacheKey, [
        'geojson'       => ['type' => 'FeatureCollection', 'features' => []],
        'profile'       => 'foot-walking',
        'range_minutes' => 15,
        'center'        => ['lat' => 4.0511, 'lng' => 9.7679],
        'cached'        => false,
    ], 3600);

    // HTTP factory that should never be called
    $neverCalled = fakeIsoHttpFactory([]);
    $service = new IsochroneService($neverCalled);
    app()->instance(IsochroneService::class, $service);

    $response = $this->getJson('/api/v1/isochrones?lat=4.0511&lng=9.7679&profile=foot-walking&range=15');

    $response->assertOk()
        ->assertJsonPath('data.cached', true);
});

it('returns 503 when ORS returns a non-200 status', function (): void {
    config(['services.ors.key' => 'test-ors-key']);

    $service = new IsochroneService(
        fakeIsoHttpFactory(['openrouteservice.org' => new ClientResponse(new Psr7Response(429))])
    );
    app()->instance(IsochroneService::class, $service);

    $this->getJson('/api/v1/isochrones?lat=4.0511&lng=9.7679')
        ->assertStatus(503);
});
