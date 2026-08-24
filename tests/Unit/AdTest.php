<?php

use App\Models\Ad;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('owner can always view full ad', function (): void {
    $owner = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    expect($ad->isUnlockedFor($owner))->toBeTrue();
});

test('guest cannot view locked ad', function (): void {
    $ad = Ad::factory()->create();
    $guest = User::factory()->create();

    expect($ad->isUnlockedFor($guest))->toBeFalse();
});

test('user who unlocked can view ad', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create();

    UnlockedAd::create([
        'user_id' => $user->id,
        'ad_id' => $ad->id,
    ]);

    expect($ad->isUnlockedFor($user))->toBeTrue();
});

test('isUnlockedFor batches the unlocked lookup into a single query per user', function (): void {
    $user = User::factory()->create();

    $unlockedA = Ad::factory()->create();
    $unlockedB = Ad::factory()->create();
    $locked = Ad::factory()->create();

    UnlockedAd::create(['user_id' => $user->id, 'ad_id' => $unlockedA->id]);
    UnlockedAd::create(['user_id' => $user->id, 'ad_id' => $unlockedB->id]);

    DB::enableQueryLog();

    $results = [
        $unlockedA->isUnlockedFor($user),
        $unlockedB->isUnlockedFor($user),
        $locked->isUnlockedFor($user),
    ];

    $unlockedAdQueries = array_filter(
        DB::getQueryLog(),
        fn (array $query): bool => str_contains((string) $query['query'], 'unlocked_ads'),
    );

    DB::disableQueryLog();

    expect($results)->toBe([true, true, false])
        ->and($unlockedAdQueries)->toHaveCount(1);
});

test('toSearchableArray exposes the expected search document shape', function (): void {
    $ad = Ad::factory()->create();
    $ad->load(['quarter.city', 'ad_type']);

    $doc = $ad->toSearchableArray();

    expect($doc)->toHaveKeys([
        'id',
        'title',
        'description',
        'price',
        'is_furnished',
        'relevance_score',
        'is_boosted',
        'attributes',
    ])
        ->and($doc['relevance_score'])->toBeInt()
        ->and($doc['is_furnished'])->toBeBool();
});
