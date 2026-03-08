<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('payment.default', 'flutterwave');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test_webhook_secret_123');
});

function makeValidWebhookPayload(string $txRef, string $status = 'successful'): array
{
    return [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $txRef,
            'status' => $status,
            'amount' => 150000,
            'currency' => 'XAF',
            'payment_type' => 'mobilemoneycameroon',
            'created_at' => now()->toISOString(),
        ],
    ];
}

function validWebhookHeaders(): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => config('payment.gateways.flutterwave.webhook_secret'),
    ];
}

// ─── SIGNATURE ───────────────────────────────────────────────────────────

it('should return 200 when webhook signature is valid', function (): void {
    $payment = Payment::factory()->pending()->create(['amount' => 150000, 'gateway' => 'flutterwave']);
    $payload = json_encode(makeValidWebhookPayload($payment->transaction_id), JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], validWebhookHeaders(), $payload);
    $response->assertSuccessful();
});

it('should return 401 when webhook has no signature header', function (): void {
    $payload = json_encode(makeValidWebhookPayload('TXN-001'), JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertUnauthorized();
});

it('should return 401 when webhook signature is tampered', function (): void {
    $payload = json_encode(makeValidWebhookPayload('TXN-001'), JSON_THROW_ON_ERROR);

    $response = $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => 'hacker_attempt_'.bin2hex(random_bytes(16)),
    ], $payload);

    $response->assertUnauthorized();
    $this->assertDatabaseMissing('payments', ['status' => PaymentStatus::SUCCESS->value]);
});

it('should not update database when signature is invalid', function (): void {
    $payment = Payment::factory()->pending()->create(['gateway' => 'flutterwave']);
    $payload = json_encode(makeValidWebhookPayload($payment->transaction_id), JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => 'wrong_signature',
    ], $payload);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::PENDING->value]);
});

// ─── TRAITEMENT ──────────────────────────────────────────────────────────

it('should mark payment as success when webhook is charge completed', function (): void {
    $payment = Payment::factory()->pending()->create(['amount' => 150000, 'gateway' => 'flutterwave']);
    $payload = json_encode(makeValidWebhookPayload($payment->transaction_id, 'successful'), JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], validWebhookHeaders(), $payload);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});

it('should fire PaymentSucceeded event when webhook marks payment success', function (): void {
    Event::fake();
    $payment = Payment::factory()->pending()->create(['amount' => 150000, 'gateway' => 'flutterwave']);
    $payload = json_encode(makeValidWebhookPayload($payment->transaction_id), JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], validWebhookHeaders(), $payload);

    Event::assertDispatched(PaymentSucceeded::class, fn ($event) => $event->payment->id === $payment->id);
});

// ─── IDEMPOTENCE ─────────────────────────────────────────────────────────

it('should be idempotent when same webhook is received multiple times', function (): void {
    Event::fake();
    $payment = Payment::factory()->pending()->create(['amount' => 150000, 'gateway' => 'flutterwave']);
    $payload = json_encode(makeValidWebhookPayload($payment->transaction_id), JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], validWebhookHeaders(), $payload);
    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], validWebhookHeaders(), $payload);
    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], validWebhookHeaders(), $payload);

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

// ─── SÉCURITÉ AVANCÉE ────────────────────────────────────────────────────

it('should not downgrade success to failed via fake webhook', function (): void {
    $payment = Payment::factory()->success()->create(['amount' => 150000, 'gateway' => 'flutterwave']);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $payment->transaction_id,
            'status' => 'failed',
            'amount' => 150000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], validWebhookHeaders(), $payload);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});
