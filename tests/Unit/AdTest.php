<?php

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;



test('owner can always view full ad', function () {
    $owner = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    expect($ad->isUnlockedFor($owner))->toBeTrue();
});

test('guest cannot view locked ad', function () {
    $ad = Ad::factory()->create();
    $guest = User::factory()->create();

    expect($ad->isUnlockedFor($guest))->toBeFalse();
});

test('user who paid can view ad', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->create();

    Payment::factory()->create([
        'user_id' => $user->id,
        'ad_id' => $ad->id,
        'type' => PaymentType::UNLOCK,
        'status' => PaymentStatus::SUCCESS
    ]);

    expect($ad->isUnlockedFor($user))->toBeTrue();
});
