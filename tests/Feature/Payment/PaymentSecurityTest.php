<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

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

it('cancelled payment cannot be overwritten by webhook', function (): void {
    Event::fake();
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
        'status' => PaymentStatus::CANCELLED->value,
    ]);

    Event::assertNotDispatched(PaymentSucceeded::class);
});
