<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const FEDAPAY_TEST_SECRET = 'fedapay_test_webhook_secret_xyz';

beforeEach(function (): void {
    config()->set('payment.default', 'fedapay');
    config()->set('payment.gateways.fedapay.secret_key', 'sk_sandbox_fake');
    config()->set('payment.gateways.fedapay.webhook_secret', FEDAPAY_TEST_SECRET);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fedaPayload(string $txRef, string $status = 'approved'): array
{
    return [
        'name' => 'transaction.approved',
        'entity' => [
            'id' => random_int(1000, 9999),
            'status' => $status,
            'amount' => 15000,
            'currency' => ['iso' => 'XAF'],
            'payment_method' => 'mobile_money',
            'custom_metadata' => ['tx_ref' => $txRef],
        ],
    ];
}

function fedaHeaders(array $payload): array
{
    $signature = hash_hmac('sha256', (string) json_encode($payload), FEDAPAY_TEST_SECRET);

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FEDAPAY_SIGNATURE' => $signature,
    ];
}

// ---------------------------------------------------------------------------
// TC-FP-01 — Signature valide → 200
// ---------------------------------------------------------------------------

it('returns 200 when FedaPay webhook signature is valid', function (): void {
    $payment = Payment::factory()->pending()->create([
        'amount' => 15000,
        'gateway' => 'fedapay',
    ]);

    $payload = fedaPayload($payment->transaction_id);

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/fedapay',
        [],
        [],
        [],
        fedaHeaders($payload),
        (string) json_encode($payload),
    );

    $response->assertSuccessful();
});

// ---------------------------------------------------------------------------
// TC-FP-02 — Signature absente → 401
// ---------------------------------------------------------------------------

it('returns 401 when FedaPay webhook has no signature header', function (): void {
    $payload = fedaPayload('TXN-NOSIG');

    $response = $this->call(
        'POST',
        '/api/v1/webhooks/fedapay',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        (string) json_encode($payload),
    );

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// TC-FP-03 — Signature falsifiée → 401, pas de mutation BDD
// ---------------------------------------------------------------------------

it('returns 401 and does not update database when FedaPay signature is tampered', function (): void {
    $payment = Payment::factory()->pending()->create(['gateway' => 'fedapay']);
    $payload = fedaPayload($payment->transaction_id);

    $this->call(
        'POST',
        '/api/v1/webhooks/fedapay',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FEDAPAY_SIGNATURE' => 'tampered_'.bin2hex(random_bytes(8))],
        (string) json_encode($payload),
    );

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::PENDING->value,
    ]);
});

// ---------------------------------------------------------------------------
// TC-FP-04 — Webhook approuvé → statut SUCCESS
// ---------------------------------------------------------------------------

it('marks payment as success when FedaPay transaction is approved', function (): void {
    $payment = Payment::factory()->pending()->create([
        'amount' => 15000,
        'gateway' => 'fedapay',
    ]);

    $payload = fedaPayload($payment->transaction_id, 'approved');

    $this->call('POST', '/api/v1/webhooks/fedapay', [], [], [], fedaHeaders($payload), (string) json_encode($payload));

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});

// ---------------------------------------------------------------------------
// TC-FP-05 — Webhook approuvé → événement PaymentSucceeded dispatché
// ---------------------------------------------------------------------------

it('dispatches PaymentSucceeded event when FedaPay webhook marks payment as success', function (): void {
    Event::fake();
    $payment = Payment::factory()->pending()->create([
        'amount' => 15000,
        'gateway' => 'fedapay',
    ]);

    $payload = fedaPayload($payment->transaction_id);

    $this->call('POST', '/api/v1/webhooks/fedapay', [], [], [], fedaHeaders($payload), (string) json_encode($payload));

    Event::assertDispatched(PaymentSucceeded::class, fn ($e) => $e->payment->id === $payment->id);
});

// ---------------------------------------------------------------------------
// TC-FP-06 — Idempotence : même webhook reçu 3× → événement dispatché 1× seulement
// ---------------------------------------------------------------------------

it('is idempotent when the same FedaPay webhook is received multiple times', function (): void {
    Event::fake();
    $payment = Payment::factory()->pending()->create([
        'amount' => 15000,
        'gateway' => 'fedapay',
    ]);

    $payload = fedaPayload($payment->transaction_id);
    $headers = fedaHeaders($payload);
    $body = (string) json_encode($payload);

    $this->call('POST', '/api/v1/webhooks/fedapay', [], [], [], $headers, $body);
    $this->call('POST', '/api/v1/webhooks/fedapay', [], [], [], $headers, $body);
    $this->call('POST', '/api/v1/webhooks/fedapay', [], [], [], $headers, $body);

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

// ---------------------------------------------------------------------------
// TC-FP-07 — Webhook "declined" → statut FAILED
// ---------------------------------------------------------------------------

it('marks payment as failed when FedaPay transaction is declined', function (): void {
    $payment = Payment::factory()->pending()->create([
        'amount' => 15000,
        'gateway' => 'fedapay',
    ]);

    $payload = fedaPayload($payment->transaction_id, 'declined');

    $this->call('POST', '/api/v1/webhooks/fedapay', [], [], [], fedaHeaders($payload), (string) json_encode($payload));

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::FAILED->value,
    ]);
});

// ---------------------------------------------------------------------------
// TC-FP-08 — Pas de dégradation : SUCCESS → FAILED impossible via faux webhook
// ---------------------------------------------------------------------------

it('cannot downgrade a successful payment to failed via a fake FedaPay webhook', function (): void {
    $payment = Payment::factory()->success()->create([
        'amount' => 15000,
        'gateway' => 'fedapay',
    ]);

    $payload = fedaPayload($payment->transaction_id, 'declined');

    $this->call('POST', '/api/v1/webhooks/fedapay', [], [], [], fedaHeaders($payload), (string) json_encode($payload));

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});
