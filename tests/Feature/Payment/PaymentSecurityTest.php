<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function paymentSecurityGeniusPayWebhookHeaders(int $timestamp, string $signature): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_WEBHOOK_EVENT' => 'payment.success',
    ];
}

function paymentSecurityGeniusPayWebhookSignature(string $secret, int $timestamp, array $payload): string
{
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return hash_hmac('sha256', $timestamp.'.'.$encoded, $secret);
}

beforeEach(function (): void {
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.geniuspay.redirect_url', 'https://test.app/payment/callback');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test_webhook_secret_123');
});

// ─── SERVER-SIDE PRICE RESOLUTION ────────────────────────────────────────

it('resolves credit price from PointPackage, not client', function (): void {
    Event::fake();
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SEC',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-SEC',
            ],
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'plan_id' => $package->id,
    ])->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'amount' => 3000,
    ]);
});

it('rejects credit purchase with inactive package', function (): void {
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => false]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'plan_id' => $package->id,
    ])->assertStatus(422);
});

it('rejects credit purchase with non-existent plan_id', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'plan_id' => '00000000-0000-0000-0000-000000000000',
    ])->assertStatus(422);
});

// ─── AMOUNT/CURRENCY VERIFICATION ───────────────────────────────────────

it('marks payment FAILED when gateway returns mismatched amount', function (): void {
    Event::fake();

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'gateway_response' => ['genius_reference' => 'MTX-MISMATCH'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-MISMATCH',
                'status' => 'completed',
                'amount' => 1,
                'currency' => 'XAF',
            ],
        ], 200),
    ]);

    $service = app(PaymentService::class);
    $result = $service->syncPaymentStatus($payment);

    expect($result->status)->toBe(PaymentStatus::FAILED);
    Event::assertDispatched(PaymentFailed::class);
});

it('accepts XOF from geniuspay when ledger currency is XAF', function (): void {
    Event::fake();

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'gateway_response' => ['genius_reference' => 'SANDBOX-XOF-LEDGER'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX-XOF-LEDGER',
                'status' => 'completed',
                'amount' => 5000,
                'currency' => 'XOF',
            ],
        ], 200),
    ]);

    $service = app(PaymentService::class);
    $result = $service->syncPaymentStatus($payment);

    expect($result->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('marks payment FAILED when gateway returns wrong currency', function (): void {
    Event::fake();

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'gateway_response' => ['genius_reference' => 'MTX-CURRENCY'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-CURRENCY',
                'status' => 'completed',
                'amount' => 5000,
                'currency' => 'USD',
            ],
        ], 200),
    ]);

    $service = app(PaymentService::class);
    $result = $service->syncPaymentStatus($payment);

    expect($result->status)->toBe(PaymentStatus::FAILED);
    Event::assertDispatched(PaymentFailed::class);
});

it('webhook with mismatched amount marks payment FAILED', function (): void {
    Event::fake();
    $secret = config('payment.gateways.geniuspay.webhook_secret');

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'geniuspay',
        'amount' => 5000,
    ]);

    $timestamp = time();
    $payloadArray = [
        'event' => 'payment.success',
        'data' => [
            'reference' => 'MTX-MISMATCH',
            'status' => 'completed',
            'amount' => 1,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => $payment->transaction_id],
        ],
    ];
    $signature = paymentSecurityGeniusPayWebhookSignature($secret, $timestamp, $payloadArray);
    $payload = json_encode($payloadArray, JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/geniuspay', [], [], [], paymentSecurityGeniusPayWebhookHeaders($timestamp, $signature), $payload)->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::FAILED->value,
    ]);

    Event::assertDispatched(PaymentFailed::class);
    Event::assertNotDispatched(PaymentSucceeded::class);
});

// ─── CANCELLATION ───────────────────────────────────────────────────────

it('user can cancel their own pending payment', function (): void {
    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/payments/cancel_payment', ['tx_ref' => $payment->transaction_id])
        ->assertSuccessful()
        ->assertJsonPath('status', 'cancelled');

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::CANCELLED->value,
    ]);
});

it('user cannot cancel another users payment', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $owner->id,
        'gateway' => 'geniuspay',
    ]);

    $this->actingAs($intruder)
        ->postJson('/api/v1/payments/cancel_payment', ['tx_ref' => $payment->transaction_id])
        ->assertNotFound();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::PENDING->value,
    ]);
});

it('user cannot cancel an already successful payment', function (): void {
    $user = User::factory()->create();
    $payment = Payment::factory()->success()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/payments/cancel_payment', ['tx_ref' => $payment->transaction_id])
        ->assertStatus(409);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});

it('guest cannot cancel a payment', function (): void {
    $this->postJson('/api/v1/payments/cancel_payment', ['tx_ref' => 'KH-TEST'])
        ->assertUnauthorized();
});

// ─── CANCELLED STATUS VIA VERIFY ────────────────────────────────────────

it('verify sets CANCELLED when gateway returns cancelled status', function (): void {
    Event::fake();

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 5000,
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-CANCEL',
                'status' => 'cancelled',
                'amount' => 5000,
                'currency' => 'XAF',
            ],
        ], 200),
    ]);

    $payment->forceFill(['gateway_response' => ['genius_reference' => 'MTX-CANCEL']])->save();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/verify_payment', ['tx_ref' => $payment->transaction_id])
        ->assertSuccessful()
        ->assertJsonPath('status', 'cancelled');

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::CANCELLED->value,
    ]);
});

// ─── IDEMPOTENCY / TERMINAL STATE ───────────────────────────────────────

it('reopens a CANCELLED payment when a signed webhook confirms a real charge (orphan-debit guard)', function (): void {
    // Behaviour change (Stripe + Flutterwave): silently ignoring a signed
    // success webhook on a locally-CANCELLED row caused orphan debits in
    // multi-tab scenarios — the gateway took the money but our row said
    // « cancelled » so credits were never delivered. We now log critical
    // and reopen the row so post-payment actions run, while support is
    // alerted via the Log::critical entry. Spoofed webhooks remain
    // blocked upstream by signature verification.
    Event::fake();
    Log::spy();
    $secret = config('payment.gateways.geniuspay.webhook_secret');

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'status' => PaymentStatus::CANCELLED,
        'amount' => 5000,
    ]);

    $timestamp = time();
    $payloadArray = [
        'event' => 'payment.success',
        'data' => [
            'reference' => 'MTX-ORPHAN',
            'status' => 'completed',
            'amount' => 5000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => $payment->transaction_id],
        ],
    ];
    $signature = paymentSecurityGeniusPayWebhookSignature($secret, $timestamp, $payloadArray);
    $payload = json_encode($payloadArray, JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/geniuspay', [], [], [], paymentSecurityGeniusPayWebhookHeaders($timestamp, $signature), $payload)->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    Event::assertDispatched(PaymentSucceeded::class);
    Log::shouldHaveReceived('critical')
        ->withArgs(fn ($message) => str_contains((string) $message, 'orphan debit'))
        ->once();
});

it('passes allowed callback_url to geniuspay for credit purchase', function (): void {
    config()->set('app.frontend_url', 'http://localhost:3000');
    Event::fake();
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SEC',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-SEC',
            ],
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);
    $user = User::factory()->create();

    $callback = 'http://localhost:3000/credits/callback?ad_id=abc';

    $this->actingAs($user)->postJson("/api/v1/credits/purchase/{$package->id}", [
        'callback_url' => $callback,
    ])->assertSuccessful();

    Http::assertSent(function (Request $request) use ($callback): bool {
        if (!str_contains($request->url(), 'pay.genius.ci')) {
            return false;
        }
        $data = $request->data();

        return ($data['success_url'] ?? null) === $callback
            && ($data['error_url'] ?? null) === $callback;
    });
});

it('rejects credit purchase with disallowed callback_url host', function (): void {
    config()->set('app.frontend_url', 'http://localhost:3000');
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson("/api/v1/credits/purchase/{$package->id}", [
        'callback_url' => 'https://evil.example/phish',
    ])->assertStatus(422)
        ->assertJsonFragment(['message' => 'URL de retour non autorisée.']);
});
