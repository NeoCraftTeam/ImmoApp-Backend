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

beforeEach(function (): void {
    config()->set('payment.default', 'flutterwave');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test_webhook_secret_123');
    config()->set('payment.gateways.flutterwave.redirect_url', 'https://test.app/payment/callback');
});

// ─── SERVER-SIDE PRICE RESOLUTION ────────────────────────────────────────

it('resolves credit price from PointPackage, not client', function (): void {
    Event::fake();
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/test'],
        ], 200),
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
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'successful',
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

it('marks payment FAILED when gateway returns wrong currency', function (): void {
    Event::fake();

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'successful',
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
    $secret = config('payment.gateways.flutterwave.webhook_secret');

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $payment->transaction_id,
            'status' => 'successful',
            'amount' => 1,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => $secret,
    ], $payload)->assertSuccessful();

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
        'gateway' => 'flutterwave',
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
        'gateway' => 'flutterwave',
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
        'gateway' => 'flutterwave',
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
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'cancelled',
                'amount' => 5000,
                'currency' => 'XAF',
            ],
        ], 200),
    ]);

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
    $secret = config('payment.gateways.flutterwave.webhook_secret');

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'status' => PaymentStatus::CANCELLED,
        'amount' => 5000,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $payment->transaction_id,
            'status' => 'successful',
            'amount' => 5000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => $secret,
    ], $payload)->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    Event::assertDispatched(PaymentSucceeded::class);
    Log::shouldHaveReceived('critical')
        ->withArgs(fn ($message) => str_contains((string) $message, 'orphan debit'))
        ->once();
});

it('passes allowed callback_url to flutterwave for credit purchase', function (): void {
    config()->set('app.frontend_url', 'http://localhost:3000');
    Event::fake();
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/test'],
        ], 200),
    ]);

    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);
    $user = User::factory()->create();

    $callback = 'http://localhost:3000/credits/callback?ad_id=abc';

    $this->actingAs($user)->postJson("/api/v1/credits/purchase/{$package->id}", [
        'callback_url' => $callback,
    ])->assertSuccessful();

    Http::assertSent(function (Request $request) use ($callback): bool {
        if (!str_contains($request->url(), 'api.flutterwave.com')) {
            return false;
        }
        $data = $request->data();

        return ($data['redirect_url'] ?? null) === $callback;
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
