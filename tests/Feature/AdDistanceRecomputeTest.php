<?php

declare(strict_types=1);

use App\Jobs\RecomputeAdDistancesJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\Geo\NeighborhoodScorecardService;
use Clickbar\Magellan\Data\Geometries\Point;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeAdWithLocation(?Point $location = null): Ad
{
    $user = User::factory()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $user, $location): void {
        $ad = Ad::factory()->create([
            'user_id' => $user->id,
            'status' => 'available',
            'location' => $location ?? Point::makeGeodetic(4.0511, 9.7679),
        ]);
    });

    return $ad;
}

function fakeOverpassPayload(array $elements = []): ClientResponse
{
    return new ClientResponse(
        new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(['elements' => $elements]))
    );
}

function fakeDistancesHttpFactory(array $urlMap): HttpFactory
{
    return new class($urlMap) extends HttpFactory
    {
        public function __construct(private readonly array $urlMap) {}

        public function post($url, $data = []): ClientResponse
        {
            foreach ($this->urlMap as $pattern => $response) {
                if (str_contains((string) $url, (string) $pattern)) {
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

        public function connectTimeout(int $seconds): static
        {
            return $this;
        }

        public function retry(int $times, int $sleep = 0): static
        {
            return $this;
        }

        public function asForm(): static
        {
            return $this;
        }
    };
}

// ─── Job behaviour ───────────────────────────────────────────────────────────

it('populates the four scorecard-backed distance columns on an ad', function (): void {
    // ORS off → service falls back to haversine; deterministic without a second fake
    config(['services.ors.key' => null]);

    // One POI per scorecard category (transport, commerce, sante, education) at
    // a known offset from (4.0511, 9.7679). The service exposes whichever is
    // nearest within each category — exact metres come from haversine.
    $elements = [
        ['type' => 'node', 'id' => 1, 'lat' => 4.0520, 'lon' => 9.7679, 'tags' => ['highway' => 'bus_stop', 'name' => 'Arrêt 1']],
        ['type' => 'node', 'id' => 2, 'lat' => 4.0515, 'lon' => 9.7680, 'tags' => ['amenity' => 'marketplace', 'name' => 'Marché']],
        ['type' => 'node', 'id' => 3, 'lat' => 4.0530, 'lon' => 9.7679, 'tags' => ['amenity' => 'clinic', 'name' => 'Clinique']],
        ['type' => 'node', 'id' => 4, 'lat' => 4.0540, 'lon' => 9.7679, 'tags' => ['amenity' => 'school', 'name' => 'École']],
    ];

    $service = new NeighborhoodScorecardService(
        fakeDistancesHttpFactory(['overpass-api.de' => fakeOverpassPayload($elements)])
    );
    app()->instance(NeighborhoodScorecardService::class, $service);

    $ad = makeAdWithLocation(Point::makeGeodetic(4.0511, 9.7679));

    // Reset any pre-existing distance values the factory might leave behind.
    $ad->forceFill([
        'distance_transport_m' => null,
        'distance_shops_m' => null,
        'distance_school_m' => null,
        'distance_hospital_m' => null,
    ])->saveQuietly();

    (new RecomputeAdDistancesJob($ad->id))->handle($service);

    $ad->refresh();

    expect($ad->distance_transport_m)->toBeInt()->toBeGreaterThan(0);
    expect($ad->distance_shops_m)->toBeInt()->toBeGreaterThan(0);
    expect($ad->distance_school_m)->toBeInt()->toBeGreaterThan(0);
    expect($ad->distance_hospital_m)->toBeInt()->toBeGreaterThan(0);
});

it('no-ops when the ad has no location', function (): void {
    $ad = makeAdWithLocation();
    $ad->getConnection()->statement(
        'UPDATE '.$ad->getConnection()->getTablePrefix().$ad->getTable().' SET location = NULL WHERE id = ?',
        [$ad->id]
    );

    $service = new NeighborhoodScorecardService(
        fakeDistancesHttpFactory(['overpass-api.de' => fakeOverpassPayload([])])
    );

    (new RecomputeAdDistancesJob($ad->id))->handle($service);

    $ad->refresh();
    expect($ad->distance_transport_m)->toBeNull();
});

// ─── Observer wiring ─────────────────────────────────────────────────────────

it('dispatches RecomputeAdDistancesJob when an ad is created with a location', function (): void {
    Bus::fake([RecomputeAdDistancesJob::class]);

    $ad = makeAdWithLocation(Point::makeGeodetic(4.0511, 9.7679));

    Bus::assertDispatched(
        RecomputeAdDistancesJob::class,
        fn (RecomputeAdDistancesJob $job): bool => $job->adId === $ad->id,
    );
});

it('dispatches RecomputeAdDistancesJob when the ad location changes', function (): void {
    $ad = makeAdWithLocation(Point::makeGeodetic(4.0511, 9.7679));

    // Fake AFTER the initial create so we only capture the location-change dispatch.
    Bus::fake([RecomputeAdDistancesJob::class]);

    $ad->update(['location' => Point::makeGeodetic(4.0600, 9.7700)]);

    Bus::assertDispatched(
        RecomputeAdDistancesJob::class,
        fn (RecomputeAdDistancesJob $job): bool => $job->adId === $ad->id,
    );
});

it('does not dispatch RecomputeAdDistancesJob when only an unrelated column changes', function (): void {
    $ad = makeAdWithLocation(Point::makeGeodetic(4.0511, 9.7679));

    Bus::fake([RecomputeAdDistancesJob::class]);

    $ad->update(['title' => 'Updated title']);

    Bus::assertNotDispatched(RecomputeAdDistancesJob::class);
});
