<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function kpayWebhookHeaders(string $signature, string $event = 'payment.completed'): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_KPAY_SIGNATURE' => $signature,
        'HTTP_X_KPAY_EVENT' => $event,
    ];
}

function kpayWebhookSignature(string $secret, string $body): string
{
    return hash_hmac('sha256', $body, $secret);
}

function kpayWebhookConfig(string $secret = 'whsec_kpay_test_secret_123'): void
{
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_key', 'kpay_test_fake');
    config()->set('payment.gateways.kpay.api_secret', 'sk_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', $secret);
}

it('valid Kpay webhook marks payment SUCCESS', function (): void {
    Event::fake();
    Mail::fake();
    $secret = 'whsec_kpay_test_secret_123';
    kpayWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-WEBHOOK-OK',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 5000,
    ]);

    $payload = [
        'event' => 'payment.completed',
        'paymentId' => 'pay_webhook_ok',
        'reference' => 'KPAY-WH-OK',
        'status' => 'COMPLETED',
        'amount' => 5000,
        'externalId' => 'KH-WEBHOOK-OK',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = kpayWebhookSignature($secret, $body);

    $this->call('POST', '/api/v1/webhooks/kpay', [], [], [], kpayWebhookHeaders($signature), $body)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    Event::assertDispatched(PaymentSucceeded::class);
});

it('accepts a webhook signed over the raw body with escaped URLs', function (): void {
    Event::fake();
    Mail::fake();
    $secret = 'whsec_kpay_test_secret_123';
    kpayWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-RAWBODYOK01',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 5000,
    ]);

    $payload = [
        'event' => 'payment.completed',
        'paymentId' => 'pay_raw',
        'reference' => 'KPAY-RAW-001',
        'status' => 'COMPLETED',
        'amount' => 5000,
        'externalId' => 'KH-RAWBODYOK01',
        'metadata' => [
            'returnUrl' => 'https://keyhome.test/payment/native-return?callback=keyhome://credits/callback',
        ],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = kpayWebhookSignature($secret, $body);

    $this->call('POST', '/api/v1/webhooks/kpay', [], [], [], kpayWebhookHeaders($signature), $body)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('Kpay webhook with invalid signature returns 401', function (): void {
    Event::fake();
    kpayWebhookConfig('whsec_kpay_test_secret_123');

    $payload = [
        'event' => 'payment.completed',
        'status' => 'COMPLETED',
        'amount' => 1000,
        'externalId' => 'KH-FAKE',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/kpay', [], [], [], kpayWebhookHeaders('bad-signature'), $body)
        ->assertUnauthorized();

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('Kpay webhook with failed status marks payment FAILED', function (): void {
    Event::fake();
    $secret = 'whsec_kpay_test_secret_123';
    kpayWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-FAILED-WH',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 3000,
    ]);

    $payload = [
        'event' => 'payment.failed',
        'paymentId' => 'pay_failed',
        'reference' => 'KPAY-FAIL',
        'status' => 'FAILED',
        'amount' => 3000,
        'externalId' => 'KH-FAILED-WH',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = kpayWebhookSignature($secret, $body);

    $this->call('POST', '/api/v1/webhooks/kpay', [], [], [], kpayWebhookHeaders($signature, 'payment.failed'), $body)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::FAILED->value,
    ]);

    Event::assertDispatched(PaymentFailed::class);
});

it('second Kpay webhook on already-SUCCESS payment is idempotent', function (): void {
    Event::fake();
    $secret = 'whsec_kpay_test_secret_123';
    kpayWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-IDEM-WH',
        'status' => PaymentStatus::SUCCESS,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 5000,
    ]);

    $payload = [
        'event' => 'payment.completed',
        'paymentId' => 'pay_idem',
        'status' => 'COMPLETED',
        'amount' => 5000,
        'externalId' => 'KH-IDEM-WH',
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = kpayWebhookSignature($secret, $body);

    $this->call('POST', '/api/v1/webhooks/kpay', [], [], [], kpayWebhookHeaders($signature), $body)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::SUCCESS->value]);
    Event::assertNotDispatched(PaymentSucceeded::class);
    Event::assertNotDispatched(PaymentFailed::class);
});
