<?php

use App\Enums\AdStatus;
use App\Models\Ad;

it('orders ads by subscription sponsorship first', function (): void {
    Ad::disableSearchSyncing();

    // Clear and create fresh test data
    Ad::query()->forceDelete();

    $sponsored = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 50,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    $boosted = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 200,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    $organic = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 0,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    // Test with regular query
    $results = Ad::visible()->publiclyListed()->orderBySponsorship()->get();

    expect($results->count())->toBeGreaterThanOrEqual(3);
    expect($results->first()->id)->toBe($sponsored->id);
    expect($results->skip(1)->first()->id)->toBe($boosted->id);
    expect($results->skip(2)->first()->id)->toBe($organic->id);
});

it('orders ads by boost_score within same sponsorship tier', function (): void {
    Ad::disableSearchSyncing();
    Ad::query()->forceDelete();

    $highBoost = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 200,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    $lowBoost = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 50,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    $results = Ad::visible()->publiclyListed()->orderBySponsorship()->get();

    expect($results->first()->id)->toBe($highBoost->id);
    expect($results->skip(1)->first()->id)->toBe($lowBoost->id);
});

it('uses created_at as tie-breaker for same sponsorship and boost', function (): void {
    Ad::disableSearchSyncing();
    Ad::query()->forceDelete();

    $newer = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 100,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subMinutes(5),
    ]);

    $older = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 100,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    $results = Ad::visible()->publiclyListed()->orderBySponsorship()->get();

    expect($results->first()->id)->toBe($newer->id);
    expect($results->skip(1)->first()->id)->toBe($older->id);
});

it('works correctly with cursor pagination', function (): void {
    Ad::disableSearchSyncing();
    Ad::query()->forceDelete();

    $sponsored = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 50,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ]);

    $boosted = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 200,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ]);

    $organic = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 0,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ]);

    $paginated = Ad::visible()->publiclyListed()->orderBySponsorship()->cursorPaginate(50);

    $items = $paginated->items();

    expect(count($items))->toBeGreaterThanOrEqual(3);
    expect($items[0]->id)->toBe($sponsored->id);
    expect($items[1]->id)->toBe($boosted->id);
    expect($items[2]->id)->toBe($organic->id);
});
