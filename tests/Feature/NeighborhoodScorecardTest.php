<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use App\Services\NeighborhoodScorecardService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('returns 404 for a non-existent ad', function (): void {
    $this->getJson('/api/v1/ads/00000000-0000-0000-0000-000000000000/neighborhood-scorecard')
        ->assertNotFound();
});

it('returns 422 when the ad has no GPS coordinates', function (): void {
    $user = User::factory()->create();
    $ad   = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $user): void {
        $ad = Ad::factory()->create(['user_id' => $user->id, 'status' => 'available', 'location' => null]);
    });

    $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard")
        ->assertUnprocessable()
        ->assertJsonPath('message', "Cette annonce n'a pas de coordonnées GPS.");
});

it('returns a scorecard from cache when the ad has GPS coordinates', function (): void {
    $user = User::factory()->create();
    $ad   = null;

    Ad::withoutSyncingToSearch(function () use (&$ad, $user): void {
        $ad = Ad::factory()->create([
            'user_id'  => $user->id,
            'status'   => 'available',
            'location' => Point::makeGeodetic(4.0511, 9.7679),
        ]);
    });

    $cached = [
        'global_score' => 72,
        'categories'   => [
            'transport'   => ['score' => 75, 'poi_count' => 3, 'label' => 'Transport',       'radius_m' => 500],
            'commerce'    => ['score' => 60, 'poi_count' => 2, 'label' => 'Commerces',       'radius_m' => 500],
            'sante'       => ['score' => 80, 'poi_count' => 2, 'label' => 'Santé',           'radius_m' => 1000],
            'education'   => ['score' => 55, 'poi_count' => 1, 'label' => 'Éducation',       'radius_m' => 1000],
            'securite'    => ['score' => 70, 'poi_count' => 1, 'label' => 'Sécurité',        'radius_m' => 1000],
            'vie_sociale' => ['score' => 50, 'poi_count' => 3, 'label' => 'Vie de quartier', 'radius_m' => 500],
        ],
        'computed_at' => now()->toIso8601String(),
    ];

    // Pre-seed cache — avoids a real Overpass HTTP call
    Cache::put('neighborhood_scorecard_4.051_9.768', $cached, 60);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonPath('data.global_score', 72)
        ->assertJsonStructure([
            'data' => [
                'global_score',
                'cached',
                'computed_at',
                'categories' => [
                    'transport'   => ['score', 'poi_count', 'label', 'radius_m'],
                    'commerce'    => ['score', 'poi_count', 'label', 'radius_m'],
                    'sante'       => ['score', 'poi_count', 'label', 'radius_m'],
                    'education'   => ['score', 'poi_count', 'label', 'radius_m'],
                    'securite'    => ['score', 'poi_count', 'label', 'radius_m'],
                    'vie_sociale' => ['score', 'poi_count', 'label', 'radius_m'],
                ],
            ],
        ]);
});

it('returns a fallback scorecard when Overpass API is unavailable', function (): void {
    $user = User::factory()->create();
    $ad   = null;

    Ad::withoutSyncingToSearch(function () use (&$ad, $user): void {
        $ad = Ad::factory()->create([
            'user_id'  => $user->id,
            'status'   => 'available',
            'location' => Point::makeGeodetic(4.0511, 9.7679),
        ]);
    });

    // Service with an HTTP factory that always returns empty elements (simulates timeout)
    $mockService = new NeighborhoodScorecardService(
        new class extends \Illuminate\Http\Client\Factory {
            public function post($url, $data = []): \Illuminate\Http\Client\Response
            {
                $response = new \GuzzleHttp\Psr7\Response(200, [], '{"elements":[]}');

                return new \Illuminate\Http\Client\Response($response);
            }
        }
    );

    app()->instance(NeighborhoodScorecardService::class, $mockService);

    $response = $this->getJson("/api/v1/ads/{$ad->id}/neighborhood-scorecard");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['global_score', 'categories', 'computed_at']])
        ->assertJsonPath('data.global_score', fn ($v) => $v >= 0 && $v <= 100);
});
