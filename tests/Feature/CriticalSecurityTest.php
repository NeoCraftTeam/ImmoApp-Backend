<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payment webhook rejects invalid signature when secret is configured', function (): void {
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'test-secret');

    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-CRITICALINVALID',
        'status' => PaymentStatus::PENDING,
        'gateway' => 'kpay',
    ]);

    $payload = [
        'event' => 'payment.completed',
        'paymentId' => 'pay_critical_invalid',
        'status' => 'COMPLETED',
        'amount' => 5000,
        'externalId' => 'KH-CRITICALINVALID',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/kpay',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KPAY_SIGNATURE' => 'invalid-signature',
            'HTTP_X_KPAY_EVENT' => 'payment.completed',
        ],
        $body
    );

    $response->assertUnauthorized();
    expect($payment->fresh()?->status)->toBe(PaymentStatus::PENDING);
});

test('payment webhook accepts valid signature and updates payment', function (): void {
    $secret = 'test-secret';
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', $secret);

    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-CRITICALVALID',
        'status' => PaymentStatus::PENDING,
        'gateway' => 'kpay',
        'amount' => 5000,
    ]);

    $payload = [
        'event' => 'payment.completed',
        'paymentId' => 'pay_critical_valid',
        'reference' => 'KPAY-CRITICAL-VALID',
        'status' => 'COMPLETED',
        'amount' => 5000,
        'currency' => 'XAF',
        'externalId' => 'KH-CRITICALVALID',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $body, $secret);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/kpay',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KPAY_SIGNATURE' => $signature,
            'HTTP_X_KPAY_EVENT' => 'payment.completed',
        ],
        $body
    );

    $response->assertOk()
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});
