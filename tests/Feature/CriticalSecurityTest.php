<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payment webhook rejects invalid signature when secret is configured', function (): void {
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', 'test-secret');

    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-CRITICALINVALID',
        'status' => PaymentStatus::PENDING,
        'gateway' => 'geniuspay',
    ]);

    $timestamp = time();
    $payload = [
        'event' => 'payment.success',
        'data' => [
            'reference' => 'MTX-CRITICALINVALID',
            'status' => 'completed',
            'amount' => 5000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => 'KH-CRITICALINVALID'],
        ],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/geniuspay',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'invalid-signature',
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_EVENT' => 'payment.success',
        ],
        $body
    );

    $response->assertUnauthorized();
    expect($payment->fresh()?->status)->toBe(PaymentStatus::PENDING);
});

test('payment webhook accepts valid signature and updates payment', function (): void {
    $secret = 'test-secret';
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', $secret);

    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-CRITICALVALID',
        'status' => PaymentStatus::PENDING,
        'gateway' => 'geniuspay',
        'amount' => 5000,
    ]);

    $timestamp = time();
    $payload = [
        'event' => 'payment.success',
        'data' => [
            'reference' => 'MTX-CRITICALVALID',
            'status' => 'completed',
            'amount' => 5000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => 'KH-CRITICALVALID'],
        ],
    ];
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $timestamp.'.'.$encoded, $secret);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/geniuspay',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WEBHOOK_EVENT' => 'payment.success',
        ],
        $encoded
    );

    $response->assertOk()
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});
