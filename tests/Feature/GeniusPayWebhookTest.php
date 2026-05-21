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
function geniusPayWebhookHeaders(string $secret, int $timestamp, string $signature): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_WEBHOOK_EVENT' => 'payment.success',
    ];
}

function geniusPayWebhookSignature(string $secret, int $timestamp, array $payload): string
{
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return hash_hmac('sha256', $timestamp.'.'.$encoded, $secret);
}

function geniusPayWebhookConfig(string $secret = 'whsec_sandbox_test_secret_123'): void
{
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', $secret);
}

it('valid GeniusPay webhook marks payment SUCCESS', function (): void {
    Event::fake();
    Mail::fake();
    $secret = 'whsec_sandbox_test_secret_123';
    geniusPayWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-WEBHOOK-OK',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 5000,
    ]);

    $timestamp = time();
    $payload = [
        'event' => 'payment.success',
        'data' => [
            'reference' => 'MTX-A1B2C3D4E5',
            'status' => 'completed',
            'amount' => 5000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => 'KH-WEBHOOK-OK'],
        ],
    ];
    $signature = geniusPayWebhookSignature($secret, $timestamp, $payload);
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/geniuspay', [], [], [], geniusPayWebhookHeaders($secret, $timestamp, $signature), $body)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    Event::assertDispatched(PaymentSucceeded::class);
});

it('GeniusPay webhook with invalid signature returns 401', function (): void {
    Event::fake();
    geniusPayWebhookConfig('whsec_sandbox_test_secret_123');

    $timestamp = time();
    $payload = [
        'event' => 'payment.success',
        'data' => [
            'status' => 'completed',
            'amount' => 1000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => 'KH-FAKE'],
        ],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/geniuspay', [], [], [], geniusPayWebhookHeaders('wrong', $timestamp, 'bad-signature'), $body)
        ->assertUnauthorized();

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('GeniusPay webhook with failed status marks payment FAILED', function (): void {
    Event::fake();
    $secret = 'whsec_sandbox_test_secret_123';
    geniusPayWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-FAILED-WH',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 3000,
    ]);

    $timestamp = time();
    $payload = [
        'event' => 'payment.failed',
        'data' => [
            'status' => 'failed',
            'amount' => 3000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => 'KH-FAILED-WH'],
        ],
    ];
    $signature = geniusPayWebhookSignature($secret, $timestamp, $payload);
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/geniuspay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_WEBHOOK_EVENT' => 'payment.failed',
    ], $body)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::FAILED->value,
    ]);

    Event::assertDispatched(PaymentFailed::class);
});

it('second GeniusPay webhook on already-SUCCESS payment is idempotent', function (): void {
    Event::fake();
    $secret = 'whsec_sandbox_test_secret_123';
    geniusPayWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-IDEM-WH',
        'status' => PaymentStatus::SUCCESS,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 5000,
    ]);

    $timestamp = time();
    $payload = [
        'event' => 'payment.success',
        'data' => [
            'status' => 'completed',
            'amount' => 5000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => 'KH-IDEM-WH'],
        ],
    ];
    $signature = geniusPayWebhookSignature($secret, $timestamp, $payload);
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/geniuspay', [], [], [], geniusPayWebhookHeaders($secret, $timestamp, $signature), $body)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::SUCCESS->value]);
    Event::assertNotDispatched(PaymentSucceeded::class);
    Event::assertNotDispatched(PaymentFailed::class);
});
