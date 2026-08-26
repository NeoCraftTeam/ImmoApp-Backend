<?php

use App\Models\Ad;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('anyone can view ad list', function (): void {
    Ad::factory(3)->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');

    $price = $response->json('data.0.price');
    expect($price)->not->toBeString();
    expect(is_int($price) || is_float($price))->toBeTrue();
});

test('single ad response structure is correct', function (): void {
    $ad = Ad::factory()->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads/'.$ad->id);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'price',
                'location' => ['latitude', 'longitude'],
                'images',
                'user' => ['id', 'firstname'],
                'quarter',
            ],
        ]);
});

// KeyScore on the ad JSON is the real neighborhood livability score (0–100),
// read cache-only from the grid entry warmed by a detail-page
// `/neighborhood-scorecard` view. AdResource never computes it, so a cold
// cache — or an ad with no GPS — yields null and cards simply omit the badge.
test('AdResource emits null keyscore when the neighborhood cache is cold', function (): void {
    $ad = Ad::factory()->create([
        'status' => 'available',
        'location' => Point::makeGeodetic(4.0511, 9.7679),
    ]);

    $this->getJson('/api/v1/ads/'.$ad->id)
        ->assertOk()
        ->assertJsonPath('data.keyscore', null);
});

test('AdResource emits null keyscore when the ad has no GPS coordinates', function (): void {
    $ad = Ad::factory()->create(['status' => 'available']);
    $ad->getConnection()->statement(
        'UPDATE '.$ad->getConnection()->getTablePrefix().$ad->getTable().' SET location = NULL WHERE id = ?',
        [$ad->id]
    );

    $this->getJson('/api/v1/ads/'.$ad->id)
        ->assertOk()
        ->assertJsonPath('data.location', null)
        ->assertJsonPath('data.keyscore', null);
});

test('AdResource emits the cached neighborhood global_score as keyscore when warm', function (): void {
    $ad = Ad::factory()->create([
        'status' => 'available',
        'location' => Point::makeGeodetic(4.0511, 9.7679),
    ]);

    // Grid-quantized key written by NeighborhoodScorecardService::compute().
    Cache::put('neighborhood_scorecard_4.051_9.768', [
        'global_score' => 82,
        'status' => 'ok',
        'computed_at' => now()->toIso8601String(),
        'categories' => [],
    ], 3600);

    $this->getJson('/api/v1/ads/'.$ad->id)
        ->assertOk()
        ->assertJsonPath('data.keyscore', 82);
});

test('AdResource suppresses keyscore when the cached scorecard is unavailable', function (): void {
    $ad = Ad::factory()->create([
        'status' => 'available',
        'location' => Point::makeGeodetic(4.0511, 9.7679),
    ]);

    Cache::put('neighborhood_scorecard_4.051_9.768', [
        'global_score' => 0,
        'status' => 'unavailable',
        'computed_at' => now()->toIso8601String(),
        'categories' => [],
    ], 3600);

    $this->getJson('/api/v1/ads/'.$ad->id)
        ->assertOk()
        ->assertJsonPath('data.keyscore', null);
});
