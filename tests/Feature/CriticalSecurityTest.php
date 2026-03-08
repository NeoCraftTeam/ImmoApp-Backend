<?php

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('guest cannot access admin registration endpoint', function (): void {
    $response = $this->postJson('/api/v1/auth/registerAdmin', [
        'firstname' => 'Admin',
        'lastname' => 'Guest',
        'email' => 'guest-admin@example.com',
        'phone_number' => '+237699000001',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
    ]);

    $response->assertUnauthorized();
});

test('non admin cannot access admin registration endpoint', function (): void {
    $agent = User::factory()->agents()->create();
    Sanctum::actingAs($agent);

    $response = $this->postJson('/api/v1/auth/registerAdmin', [
        'firstname' => 'Admin',
        'lastname' => 'Denied',
        'email' => 'denied-admin@example.com',
        'phone_number' => '+237699000002',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
    ]);

    $response->assertForbidden();
});

test('admin can register another admin through protected endpoint', function (): void {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/auth/registerAdmin', [
        'firstname' => 'Super',
        'lastname' => 'Admin',
        'email' => 'new-admin@example.com',
        'phone_number' => '+237699000003',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'new-admin@example.com',
        'role' => UserRole::ADMIN->value,
    ]);
});

test('payment webhook rejects invalid signature when secret is configured', function (): void {
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test-secret');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');

    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-CRITICALINVALID',
        'status' => PaymentStatus::PENDING,
        'gateway' => 'flutterwave',
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'status' => 'successful',
            'tx_ref' => 'KH-CRITICALINVALID',
            'amount' => 5000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/flutterwave',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_VERIF_HASH' => 'invalid-signature',
        ],
        $payload
    );

    $response->assertUnauthorized();
    expect($payment->fresh()?->status)->toBe(PaymentStatus::PENDING);
});

test('payment webhook accepts valid signature and updates payment', function (): void {
    $secret = 'test-secret';
    config()->set('payment.gateways.flutterwave.webhook_secret', $secret);
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');

    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-CRITICALVALID',
        'status' => PaymentStatus::PENDING,
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'status' => 'successful',
            'tx_ref' => 'KH-CRITICALVALID',
            'amount' => 5000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/flutterwave',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_VERIF_HASH' => $secret,
        ],
        $payload
    );

    $response->assertOk()
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});
