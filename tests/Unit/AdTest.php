<?php

use App\Models\Ad;
use App\Models\UnlockedAd;
use App\Models\User;

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
