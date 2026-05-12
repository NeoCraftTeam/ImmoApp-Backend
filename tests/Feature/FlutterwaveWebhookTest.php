<?php

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
 * Build headers for a Flutterwave webhook call (verif-hash style).
 *
 * @return array<string, string>
 */
function flutterwaveWebhookHeaders(string $secret): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => $secret,
    ];
}

function flwWebhookConfig(string $secret = 'flw-test-secret'): void
{
    config()->set('payment.default', 'flutterwave');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', $secret);
}

// -- HAPPY PATH --------------------------------------------------------------

it('valid Flutterwave webhook marks payment SUCCESS', function (): void {
    Event::fake();
    Mail::fake();
    $secret = 'flw-test-secret';
    flwWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-WEBHOOK-OK',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'status' => 'successful',
            'tx_ref' => 'KH-WEBHOOK-OK',
            'amount' => 5000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], flutterwaveWebhookHeaders($secret), $payload)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    Event::assertDispatched(PaymentSucceeded::class);
});

// -- SIGNATURE VALIDATION ----------------------------------------------------

it('Flutterwave webhook with invalid signature returns 401', function (): void {
    Event::fake();
    flwWebhookConfig('flw-test-secret');

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => ['status' => 'successful', 'tx_ref' => 'KH-FAKE', 'amount' => 1000, 'currency' => 'XAF'],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], flutterwaveWebhookHeaders('WRONG_SECRET'), $payload)
        ->assertUnauthorized();

    Event::assertNotDispatched(PaymentSucceeded::class);
});

// -- FAILED / IDEMPOTENCE ----------------------------------------------------

it('Flutterwave webhook with failed status marks payment FAILED', function (): void {
    Event::fake();
    $secret = 'flw-test-secret';
    flwWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-FAILED-WH',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 3000,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'status' => 'failed',
            'tx_ref' => 'KH-FAILED-WH',
            'amount' => 3000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], flutterwaveWebhookHeaders($secret), $payload)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::FAILED->value,
    ]);

    Event::assertDispatched(PaymentFailed::class);
});

it('second Flutterwave webhook on already-SUCCESS payment is idempotent', function (): void {
    Event::fake();
    $secret = 'flw-test-secret';
    flwWebhookConfig($secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-IDEM-WH',
        'status' => PaymentStatus::SUCCESS,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => ['status' => 'successful', 'tx_ref' => 'KH-IDEM-WH', 'amount' => 5000, 'currency' => 'XAF'],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], flutterwaveWebhookHeaders($secret), $payload)
        ->assertSuccessful();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::SUCCESS->value]);
    Event::assertNotDispatched(PaymentSucceeded::class);
    Event::assertNotDispatched(PaymentFailed::class);
});

it('webhook for unknown tx_ref is silently ignored with 200', function (): void {
    Event::fake();
    $secret = 'flw-test-secret';
    flwWebhookConfig($secret);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => ['status' => 'successful', 'tx_ref' => 'KH-DOES-NOT-EXIST', 'amount' => 1000, 'currency' => 'XAF'],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], flutterwaveWebhookHeaders($secret), $payload)
        ->assertSuccessful();

    Event::assertNotDispatched(PaymentSucceeded::class);
});
