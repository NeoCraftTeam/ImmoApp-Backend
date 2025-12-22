<?php

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('authenticated user can unlock ad', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/payments/unlock', [
        'ad_id' => $ad->id,
        'payment_method' => 'orange_money',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Ad unlocked successfully']);

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'ad_id' => $ad->id,
        'status' => PaymentStatus::SUCCESS->value,
        'payment_method' => 'orange_money',
    ]);
});

test('user cannot unlock already unlocked ad', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create();

    // Create existing payment
    Payment::factory()->create([
        'user_id' => $user->id,
        'ad_id' => $ad->id,
        'type' => PaymentType::UNLOCK,
        'status' => PaymentStatus::SUCCESS,
    ]);

    Sanctum::actingAs($user);
    $response = $this->postJson('/api/v1/payments/unlock', [
        'ad_id' => $ad->id,
        'payment_method' => 'orange_money',
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Ad already unlocked']);
});

test('guest cannot unlock ad', function (): void {
    $ad = Ad::factory()->create();

    $response = $this->postJson('/api/v1/payments/unlock', [
        'ad_id' => $ad->id,
        'payment_method' => 'orange_money',
    ]);

    $response->assertStatus(401); // Unauthorized
});
