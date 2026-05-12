<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
