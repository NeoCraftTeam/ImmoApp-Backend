<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentInitiated;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('payment.default', 'flutterwave');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test_webhook_secret_123');
    config()->set('payment.gateways.flutterwave.redirect_url', 'https://test.app/payment/callback');
});

// ─── AUTHENTIFICATION ────────────────────────────────────────────────────

it('should return 401 when user is not authenticated', function (): void {
    $response = $this->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237699000000',
    ]);

    $response->assertUnauthorized();
});

// ─── VALIDATION ──────────────────────────────────────────────────────────

it('should return 422 when type is missing', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('should return 422 when type is invalid', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'bad_type',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('should return 422 when credit is missing plan_id', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);
});

it('should ignore client-sent amount and use server price', function (): void {
    Event::fake();
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/abc123'],
        ], 200),
    ]);

    $package = PointPackage::factory()->create(['price' => 500, 'is_active' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'amount' => 999999,
        'type' => 'credit',
        'plan_id' => $package->id,
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'amount' => 500,
    ]);
});

// ─── SUCCÈS ──────────────────────────────────────────────────────────────

it('should return 200 with payment link when payload is valid', function (): void {
    Event::fake();
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/abc123'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237699000000',
        'plan_id' => $package->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['reference', 'payment_link', 'tx_ref', 'gateway'])
        ->assertJsonPath('gateway', 'flutterwave');

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'status' => PaymentStatus::PENDING->value,
        'gateway' => 'flutterwave',
    ]);

    Event::assertDispatched(PaymentInitiated::class);
});

// ─── RATE LIMITING ───────────────────────────────────────────────────────

it('should return 429 when user exceeds request rate limit', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://test'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $payload = [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237699000000',
        'plan_id' => $package->id,
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', $payload);
    }

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', $payload);
    $response->assertStatus(429);
});

// ─── ISOLATION DES DONNÉES ───────────────────────────────────────────────

it('should not expose other users data in initiate response', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://test'],
        ], 200),
    ]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 2000, 'is_active' => true]);

    Payment::factory()->count(3)->create(['user_id' => $userB->id]);

    $response = $this->actingAs($userA)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'plan_id' => $package->id,
    ]);

    $response->assertSuccessful();

    $content = $response->getContent();
    expect($content)->not->toContain((string) $userB->id);
});
