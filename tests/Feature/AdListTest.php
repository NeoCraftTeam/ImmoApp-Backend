<?php

use App\Models\Ad;
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

// Gap 9: cards on the feed render the KeyScore badge straight from the
// AdResource response when the hourly cache is warm — no per-ad fetches.
test('AdResource emits null keyscore on cold cache', function (): void {
    $ad = Ad::factory()->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads/'.$ad->id);

    $response->assertOk()
        ->assertJsonPath('data.keyscore', null);
});

test('AdResource emits cached keyscore when warm', function (): void {
    $ad = Ad::factory()->create(['status' => 'available']);

    // Same key shape as KeyScoreController::show — hourly bucket.
    $cacheKey = 'keyscore_'.$ad->id.'_'.now()->format('Ymd_H');
    Cache::put($cacheKey, [
        'score' => 82,
        'breakdown' => [],
        'label' => 'Très bon',
    ], 3600);

    $response = $this->getJson('/api/v1/ads/'.$ad->id);

    $response->assertOk()
        ->assertJsonPath('data.keyscore', 82);
});
