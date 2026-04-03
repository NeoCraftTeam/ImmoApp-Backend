<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use App\Services\NeighborhoodScorecardService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Psr7\Response as Psr7Response;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeScorecardAd(?Point $location = null): Ad
{
    $user = User::factory()->create();
    $ad   = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $user, $location): void {
        $ad = Ad::factory()->create([
            'user_id'  => $user->id,
            'status'   => 'available',
            'location' => $location ?? Point::makeGeodetic(4.0511, 9.7679),
        ]);
    });

    return $ad;
}

function fakeOverpassResponse(array $elements = []): ClientResponse
{
    return new ClientResponse(
        new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(['elements' => $elements]))
    );
}

function fakeOrsResponse(array $distances): ClientResponse
{
    return new ClientResponse(
        new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(['distances' => [$distances]]))
    );
}

function fakeOrsError(): ClientResponse
{
    return new ClientResponse(new Psr7Response(429));
}

function fakeHttpFactory(array $urlMap): \Illuminate\Http\Client\Factory
{
    $factory = new class ($urlMap) extends \Illuminate\Http\Client\Factory {
        public function __construct(private array $urlMap) {}

        public function post($url, $data = []): ClientResponse
        {
            foreach ($this->urlMap as $pattern => $response) {
                if (str_contains($url, $pattern)) {
                    return $response;
                }
            }
            return new ClientResponse(new Psr7Response(503));
        }

        public function withHeaders(array $headers): static
        {
            return $this;
        }

        public function timeout(int $seconds): static
        {
            return $this;
        }

        public function retry(int $times, int $sleep = 0): static
        {
            return $this;
        }
    };

    return $factory;
}

// ─── HTTP-level tests ─────────────────────────────────────────────────────────

it('returns 404 for a non-existent ad', function (): void {
    $this->getJson('/api/v1/ads/00000000-0000-0000-0000-000000000000/neighborhood-scorecard')
        ->assertNotFound();
});

it('returns 422 when the ad has no GPS coordinates', function (): void {
    $ad = makeScorecardAd();
    // Null out location via raw SQL on the model's own connection (avoids schema-prefix issues)
    $ad->getConnection()->statement(
        'UPDATE '.$ad->getConnection()->getTablePrefix().$ad->getTable().' SET location = NULL WHERE id = ?',
        [$ad->id]
    );

    $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard")
        ->assertUnprocessable()
        ->assertJsonPath('message', "Cette annonce n'a pas de coordonnées GPS.");
});

// ─── Cache tests ──────────────────────────────────────────────────────────────

it('serves a v2 cached scorecard without re-computing', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    $cached = [
        'global_score' => 68,
        'status'       => 'ok',
        'computed_at'  => now()->toIso8601String(),
        'categories'   => [
            'transport'   => ['score' => 75, 'poi_count' => 3, 'label' => 'Transport',       'radius_m' => 500,  'nearest_poi' => ['osm_id' => '1', 'name' => 'Gare routière', 'distance_m' => 210, 'mode' => 'walking']],
            'commerce'    => ['score' => 60, 'poi_count' => 2, 'label' => 'Commerces',       'radius_m' => 500,  'nearest_poi' => null],
            'sante'       => ['score' => 80, 'poi_count' => 2, 'label' => 'Santé',           'radius_m' => 1000, 'nearest_poi' => null],
            'education'   => ['score' => 55, 'poi_count' => 1, 'label' => 'Éducation',       'radius_m' => 1000, 'nearest_poi' => null],
            'securite'    => ['score' => 70, 'poi_count' => 1, 'label' => 'Sécurité',        'radius_m' => 1000, 'nearest_poi' => null],
            'vie_sociale' => ['score' => 50, 'poi_count' => 3, 'label' => 'Vie de quartier', 'radius_m' => 500,  'nearest_poi' => null],
        ],
    ];

    Cache::put('neighborhood_scorecard_4.051_9.768', $cached, 60);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.global_score', 68)
        ->assertJsonPath('data.cached', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.categories.transport.nearest_poi.name', 'Gare routière')
        ->assertJsonPath('data.categories.transport.nearest_poi.mode', 'walking');
});

it('invalidates a legacy v1 cache entry and re-computes', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    // v1 entry: categories have NO nearest_poi key
    $legacyCache = [
        'global_score' => 40,
        'computed_at'  => now()->toIso8601String(),
        'categories'   => [
            'transport'   => ['score' => 30, 'poi_count' => 1, 'label' => 'Transport',       'radius_m' => 500],
            'commerce'    => ['score' => 0,  'poi_count' => 0, 'label' => 'Commerces',       'radius_m' => 500],
            'sante'       => ['score' => 10, 'poi_count' => 0, 'label' => 'Santé',           'radius_m' => 1000],
            'education'   => ['score' => 0,  'poi_count' => 0, 'label' => 'Éducation',       'radius_m' => 1000],
            'securite'    => ['score' => 25, 'poi_count' => 0, 'label' => 'Sécurité',        'radius_m' => 1000],
            'vie_sociale' => ['score' => 0,  'poi_count' => 0, 'label' => 'Vie de quartier', 'radius_m' => 500],
        ],
    ];
    Cache::put('neighborhood_scorecard_4.051_9.768', $legacyCache, 3600);

    // Inject a service that returns empty POIs (simulates Overpass OK but empty area)
    $service = new NeighborhoodScorecardService(
        fakeHttpFactory(['overpass-api.de' => fakeOverpassResponse([])])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    // Response must have nearest_poi key (v2 format), not the stale v1 score
    $response->assertOk()
        ->assertJsonPath('data.cached', false)
        ->assertJsonStructure(['data' => ['categories' => ['transport' => ['nearest_poi']]]]);
});

// ─── Overpass / scoring tests ─────────────────────────────────────────────────

it('returns status=unavailable and short-TTL response when Overpass fails', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    $service = new NeighborhoodScorecardService(
        fakeHttpFactory(['overpass-api.de' => new ClientResponse(new Psr7Response(503))])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard")
        ->assertOk()
        ->assertJsonPath('data.status', 'unavailable');
});

it('returns named nearest_poi and ORS walking distance when ORS is configured', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    config(['services.ors.key' => 'test-ors-key']);

    // One bus stop 300 m away
    $elements = [[
        'type' => 'node', 'id' => 987654,
        'lat' => 4.0525, 'lon' => 9.7679,
        'tags' => ['highway' => 'bus_stop', 'name' => 'Arrêt Marché Central'],
    ]];

    $service = new NeighborhoodScorecardService(
        fakeHttpFactory([
            'overpass-api.de'       => fakeOverpassResponse($elements),
            'openrouteservice.org'  => fakeOrsResponse([385.0]), // 385 m walking
        ])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.categories.transport.nearest_poi.name', 'Arrêt Marché Central')
        ->assertJsonPath('data.categories.transport.nearest_poi.mode', 'walking')
        ->assertJsonPath('data.categories.transport.nearest_poi.distance_m', 385);
});

it('falls back to mode=air when ORS returns 429 and marks status=degraded', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    config(['services.ors.key' => 'test-ors-key']);

    $elements = [[
        'type' => 'node', 'id' => 111,
        'lat' => 4.0520, 'lon' => 9.7680,
        'tags' => ['amenity' => 'marketplace', 'name' => 'Grand Marché'],
    ]];

    $service = new NeighborhoodScorecardService(
        fakeHttpFactory([
            'overpass-api.de'      => fakeOverpassResponse($elements),
            'openrouteservice.org' => fakeOrsError(),
        ])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.categories.commerce.nearest_poi.mode', 'air')
        ->assertJsonPath('data.categories.commerce.nearest_poi.name', 'Grand Marché');
});

it('returns status=ok when no ORS key is set even with POIs (haversine is by design)', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    // Explicitly no ORS key
    config(['services.ors.key' => null]);

    $elements = [[
        'type' => 'node', 'id' => 55,
        'lat' => 4.0515, 'lon' => 9.7680,
        'tags' => ['highway' => 'bus_stop', 'name' => 'Arrêt Test'],
    ]];

    $service = new NeighborhoodScorecardService(
        fakeHttpFactory(['overpass-api.de' => fakeOverpassResponse($elements)])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.categories.transport.nearest_poi.mode', 'air');
});

it('returns status=ok when ORS key is set but there are zero POIs', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    config(['services.ors.key' => 'test-ors-key']);

    $service = new NeighborhoodScorecardService(
        fakeHttpFactory(['overpass-api.de' => fakeOverpassResponse([])])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.categories.transport.nearest_poi', null);
});

it('returns null nearest_poi for categories with no POI found', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    $service = new NeighborhoodScorecardService(
        fakeHttpFactory(['overpass-api.de' => fakeOverpassResponse([])])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.categories.transport.nearest_poi', null)
        ->assertJsonPath('data.categories.transport.poi_count', 0);
});

it('computes correct global score from weighted category scores', function (): void {
    $ad = makeScorecardAd(Point::makeGeodetic(4.0511, 9.7679));

    // 3 bus stops + 2 clinics + 1 school → known scores: transport=75, sante=80, education=55
    $elements = [
        ['type' => 'node', 'id' => 1, 'lat' => 4.051, 'lon' => 9.768, 'tags' => ['highway' => 'bus_stop']],
        ['type' => 'node', 'id' => 2, 'lat' => 4.052, 'lon' => 9.768, 'tags' => ['highway' => 'bus_stop']],
        ['type' => 'node', 'id' => 3, 'lat' => 4.053, 'lon' => 9.768, 'tags' => ['highway' => 'bus_stop']],
        ['type' => 'node', 'id' => 4, 'lat' => 4.054, 'lon' => 9.768, 'tags' => ['amenity' => 'clinic']],
        ['type' => 'node', 'id' => 5, 'lat' => 4.055, 'lon' => 9.768, 'tags' => ['amenity' => 'clinic']],
        ['type' => 'node', 'id' => 6, 'lat' => 4.056, 'lon' => 9.768, 'tags' => ['amenity' => 'school']],
    ];

    $service = new NeighborhoodScorecardService(
        fakeHttpFactory(['overpass-api.de' => fakeOverpassResponse($elements)])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.categories.transport.score', 75)
        ->assertJsonPath('data.categories.transport.poi_count', 3)
        ->assertJsonPath('data.categories.sante.score', 80)
        ->assertJsonPath('data.categories.education.score', 55);

    // global = 75*0.25 + 0*0.25 + 80*0.20 + 55*0.15 + 25*0.05 + 0*0.10
    //        = 18.75  + 0      + 16      + 8.25   + 1.25   + 0      = 44.25 → 44
    $response->assertJsonPath('data.global_score', 44);
});
