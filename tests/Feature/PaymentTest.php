<?php

use App\Models\Ad;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can initialize payment for ad and unlock with points', function (): void {
    $user = User::factory()->create(['point_balance' => 10]);
    $ad = Ad::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/payments/initialize/{$ad->id}");

    $response->assertStatus(200)
        ->assertJson(['status' => 'unlocked']);

    $this->assertDatabaseHas('unlocked_ads', [
        'user_id' => $user->id,
        'ad_id' => $ad->id,
    ]);
});

test('user cannot initialize payment for already unlocked ad', function (): void {
    $user = User::factory()->create(['point_balance' => 10]);
    $ad = Ad::factory()->create();

    UnlockedAd::create([
        'user_id' => $user->id,
        'ad_id' => $ad->id,
    ]);

    Sanctum::actingAs($user);
    $response = $this->postJson("/api/v1/payments/initialize/{$ad->id}");

    $response->assertStatus(200)
        ->assertJson(['status' => 'already_unlocked']);
});

test('guest cannot initialize payment', function (): void {
    $ad = Ad::factory()->create();

    $response = $this->postJson("/api/v1/payments/initialize/{$ad->id}");

    $response->assertStatus(401);
});
