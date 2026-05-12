<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('payment.default', 'flutterwave');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test_webhook_secret_123');
});

// ─── ISOLATION DONNÉES UTILISATEURS ─────────────────────────────────────

it('should return only authenticated user payments in history', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Payment::factory()->count(3)->create(['user_id' => $userA->id, 'gateway' => 'flutterwave']);
    Payment::factory()->count(5)->create(['user_id' => $userB->id, 'gateway' => 'flutterwave']);

    $response = $this->actingAs($userA)->getJson('/api/v1/payments/history');

    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

it('should return 404 when user tries to verify another users payment', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $userB->id,
        'gateway' => 'flutterwave',
    ]);

    $response = $this->actingAs($userA)->postJson('/api/v1/payments/verify_payment', [
        'tx_ref' => $payment->transaction_id,
    ]);

    $response->assertNotFound();
});

// ─── INJECTION & MANIPULATION ────────────────────────────────────────────

it('should ignore gateway field sent by client', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://test'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 2000, 'is_active' => true]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237699000000',
        'plan_id' => $package->id,
        'gateway' => 'flutterwave',
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('payments', ['gateway' => 'flutterwave']);
});

it('should ignore status field sent by client', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://test'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 2000, 'is_active' => true]);

    $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237699000000',
        'plan_id' => $package->id,
        'status' => 'success',
    ]);

    $this->assertDatabaseHas('payments', ['status' => PaymentStatus::PENDING->value]);
});

it('should reject sql injection attempt in reference field', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/v1/payments/verify_payment', [
        'tx_ref' => "'; DROP TABLE payments; --",
    ]);

    $response->assertNotFound();
});

// ─── EXPOSITION DE DONNÉES SENSIBLES ─────────────────────────────────────

it('should never expose flutterwave secret key in api response', function (): void {
    $user = User::factory()->create();
    Payment::factory()->count(2)->create(['user_id' => $user->id, 'gateway' => 'flutterwave']);

    $response = $this->actingAs($user)->getJson('/api/v1/payments/history');
    $content = $response->getContent();

    expect($content)
        ->not->toContain('FLWSECK')
        ->not->toContain('FLW_SECRET')
        ->not->toContain('webhook_secret');
});

it('should never expose raw gateway response in api response', function (): void {
    $user = User::factory()->create();
    Payment::factory()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'gateway_response' => ['flw_ref' => 'SECRET-FLW-REF-123', 'processor_response' => 'Approved'],
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/payments/history');
    $content = $response->getContent();

    expect($content)->not->toContain('SECRET-FLW-REF-123');
});
