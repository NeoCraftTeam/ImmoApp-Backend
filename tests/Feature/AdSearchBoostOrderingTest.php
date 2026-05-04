<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Validates the boost-premium contract on the public ad search:
 * **active boosted ads must surface above non-boosted ads regardless of the
 * user-selected sort** (other than `_geoPoint`). The Meilisearch path
 * achieves this by injecting `boost_score:desc` as a primary sort; the
 * Eloquent fallback (used in tests where Scout=null) chains
 * `orderByDesc('boost_score')` before the requested sort.
 */
function makeOwnerForSearch(): User
{
    return User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
}

beforeEach(function (): void {
    config()->set('scout.driver', 'null');
});

it('surfaces boosted ads first on the default created_at sort', function (): void {
    $owner = makeOwnerForSearch();

    $oldNonBoosted = Ad::factory()->for($owner)->create([
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_boosted' => false,
        'boost_score' => 0,
        'created_at' => now()->subDays(2),
    ]);

    $newBoosted = Ad::factory()->for($owner)->create([
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_boosted' => true,
        'boost_score' => 90,
        'boost_expires_at' => now()->addDays(3),
        'created_at' => now()->subHour(),
    ]);

    $newerNonBoosted = Ad::factory()->for($owner)->create([
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_boosted' => false,
        'boost_score' => 0,
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/ads/search')->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    $boostedIndex = array_search($newBoosted->id, $ids, true);
    $newerIndex = array_search($newerNonBoosted->id, $ids, true);
    $olderIndex = array_search($oldNonBoosted->id, $ids, true);

    expect($boostedIndex)->not->toBeFalse();
    expect($newerIndex)->not->toBeFalse();
    expect($olderIndex)->not->toBeFalse();
    // Boosted ad ranks above non-boosted, regardless of created_at ordering.
    expect($boostedIndex)->toBeLessThan($newerIndex);
    expect($boostedIndex)->toBeLessThan($olderIndex);
    // Within non-boosted, newer still wins over older.
    expect($newerIndex)->toBeLessThan($olderIndex);
});

it('keeps boost premium even when sort is set to price asc', function (): void {
    $owner = makeOwnerForSearch();

    $cheapNonBoosted = Ad::factory()->for($owner)->create([
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_boosted' => false,
        'boost_score' => 0,
        'price' => 50000,
    ]);

    $expensiveBoosted = Ad::factory()->for($owner)->create([
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_boosted' => true,
        'boost_score' => 80,
        'boost_expires_at' => now()->addDays(3),
        'price' => 250000,
    ]);

    $response = $this->getJson('/api/v1/ads/search?sort=price&order=asc')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    $boostedIdx = array_search($expensiveBoosted->id, $ids, true);
    $cheapIdx = array_search($cheapNonBoosted->id, $ids, true);

    expect($boostedIdx)->toBeLessThan($cheapIdx);
});
