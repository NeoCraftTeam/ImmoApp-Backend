<?php

use App\Enums\PaymentStatus;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\User;
use App\Services\FedaPayService;
use Laravel\Sanctum\Sanctum;

test('authenticated user can initialize payment for ad', function (): void {
    $user = User::factory()->create(['firstname' => 'John', 'lastname' => 'Doe']);
    $ad = Ad::factory()->create();

    // Mock FedaPayService
    $mock = Mockery::mock(FedaPayService::class);
    $mock->shouldReceive('createPayment')
        ->once()
        ->andReturn([
            'success' => true,
            'url' => 'https://fedapay.com/pay/123',
            'transaction_id' => '12345',
        ]);
    $this->app->instance(FedaPayService::class, $mock);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/payments/initialize/{$ad->id}");

    $response->assertStatus(200)
        ->assertJson([
            'payment_url' => 'https://fedapay.com/pay/123',
            'message' => 'Redirigez l\'utilisateur vers cette URL pour payer.',
        ]);

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'ad_id' => $ad->id,
        'status' => PaymentStatus::PENDING->value,
        'transaction_id' => '12345',
    ]);
});

test('user cannot initialize payment for already unlocked ad', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create();

    // Create existing successful payment
    Payment::factory()->create([
        'user_id' => $user->id,
        'ad_id' => $ad->id,
        'status' => PaymentStatus::SUCCESS,
    ]);

    Sanctum::actingAs($user);
    $response = $this->postJson("/api/v1/payments/initialize/{$ad->id}");

    $response->assertStatus(200)
        ->assertJson(['status' => 'already_paid']);
});

test('guest cannot initialize payment', function (): void {
    $ad = Ad::factory()->create();

    $response = $this->postJson("/api/v1/payments/initialize/{$ad->id}");

    $response->assertStatus(401);
});
