<?php

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

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
    config()->set('services.fedapay.webhook_secret', 'test-secret');

    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-critical-invalid',
        'status' => PaymentStatus::PENDING,
    ]);

    $payload = json_encode([
        'event' => 'transaction.approved',
        'entity' => ['id' => 'txn-critical-invalid'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => 'invalid-signature',
        ],
        $payload
    );

    $response->assertUnauthorized();
    expect($payment->fresh()?->status)->toBe(PaymentStatus::PENDING);
});

test('payment webhook accepts valid signature and updates payment', function (): void {
    $secret = 'test-secret';
    config()->set('services.fedapay.webhook_secret', $secret);

    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-critical-valid',
        'status' => PaymentStatus::PENDING,
    ]);

    $payload = json_encode([
        'event' => 'transaction.approved',
        'entity' => ['id' => 'txn-critical-valid'],
    ], JSON_THROW_ON_ERROR);

    // Controller expects "t=<timestamp>,v1=<hmac(timestamp.'.'.payload, secret)>"
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => "t={$timestamp},v1={$signature}",
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
