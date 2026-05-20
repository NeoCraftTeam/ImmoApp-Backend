<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentInitiated;
use App\Events\PaymentSucceeded;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.geniuspay.base_url', 'https://pay.genius.ci/api/v1/merchant');
    config()->set('payment.gateways.geniuspay.redirect_url', 'https://test.app/payment/callback');
});

// ─── CRÉATION ────────────────────────────────────────────────────────────

it('should create pending payment in database when payment is initiated', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SVC-001',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-SVC-001',
            ],
        ], 201),
    ]);

    $user = User::factory()->create();
    $service = app(PaymentService::class);

    $result = $service->createPayment($user, [
        'amount' => 150000,
        'type' => 'unlock',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237699000000',
    ]);

    expect($result['payment'])->toBeInstanceOf(Payment::class);

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'status' => PaymentStatus::PENDING->value,
        'gateway' => 'geniuspay',
        'amount' => 150000,
    ]);

    Http::assertSent(function (Request $request) use ($result): bool {
        $successUrl = (string) ($request->data()['success_url'] ?? '');

        return str_contains($successUrl, 'tx_ref='.urlencode($result['tx_ref']));
    });
});

it('should return payment link when payment is created', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SVC-001',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-SVC-001',
            ],
        ], 201),
    ]);

    $user = User::factory()->create();
    $service = app(PaymentService::class);

    $result = $service->createPayment($user, [
        'amount' => 150000,
        'type' => 'unlock',
    ]);

    expect($result)
        ->toHaveKey('link')
        ->and($result['link'])->toContain('pay.genius.ci/checkout');
});

it('should fire PaymentInitiated event when payment is created', function (): void {
    Event::fake();
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SVC-001',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-SVC-001',
            ],
        ], 201),
    ]);

    $user = User::factory()->create();
    $service = app(PaymentService::class);

    $service->createPayment($user, [
        'amount' => 150000,
        'type' => 'unlock',
    ]);

    Event::assertDispatched(PaymentInitiated::class);
});

it('should mark payment as failed when gateway throws exception', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => false,
            'error' => ['message' => 'Bad request'],
        ], 400),
    ]);

    $user = User::factory()->create();
    $service = app(PaymentService::class);

    try {
        $service->createPayment($user, [
            'amount' => 150000,
            'type' => 'unlock',
        ]);
    } catch (PaymentGatewayException) {
        // Expected
    }

    // No payment should be in success state
    $this->assertDatabaseMissing('payments', [
        'user_id' => $user->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});

// ─── VÉRIFICATION ────────────────────────────────────────────────────────

it('should mark payment as success when gateway confirms payment', function (): void {
    Event::fake();

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-VERIFY-OK',
                'status' => 'completed',
                'amount' => 10000,
                'currency' => 'XAF',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 10000,
        'gateway_response' => ['genius_reference' => 'MTX-VERIFY-OK'],
    ]);

    $service = app(PaymentService::class);
    $updated = $service->syncPaymentStatus($payment);

    expect($updated->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('reopens FAILED geniuspay payment when sync confirms completed', function (): void {
    Event::fake();

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX-REOPEN-001',
                'status' => 'completed',
                'amount' => 10000,
                'currency' => 'XOF',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => PaymentStatus::FAILED,
        'gateway' => 'geniuspay',
        'amount' => 10000,
        'gateway_response' => ['genius_reference' => 'SANDBOX-REOPEN-001'],
    ]);

    $service = app(PaymentService::class);
    $updated = $service->syncPaymentStatus($payment);

    expect($updated->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('should return cached success without calling gateway when already paid', function (): void {
    Http::preventStrayRequests();

    $payment = Payment::factory()->success()->create([
        'gateway' => 'geniuspay',
    ]);

    $service = app(PaymentService::class);
    $result = $service->syncPaymentStatus($payment);

    expect($result->status)->toBe(PaymentStatus::SUCCESS);
    Http::assertNothingSent();
});

it('should fire PaymentSucceeded event only once for duplicate webhooks', function (): void {
    Event::fake();

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'geniuspay',
        'amount' => 150000,
    ]);

    $secret = config('payment.gateways.geniuspay.webhook_secret');
    $timestamp = time();
    $webhookPayload = [
        'event' => 'payment.success',
        'data' => [
            'reference' => 'MTX-DUP-001',
            'status' => 'completed',
            'amount' => 150000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => $payment->transaction_id],
        ],
    ];
    $encoded = json_encode($webhookPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $timestamp.'.'.$encoded, (string) $secret);
    $headers = [
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Timestamp' => (string) $timestamp,
        'X-Webhook-Event' => 'payment.success',
    ];

    $service = app(PaymentService::class);

    $service->processWebhook($webhookPayload, $headers, 'geniuspay');
    $service->processWebhook($webhookPayload, $headers, 'geniuspay');
    $service->processWebhook($webhookPayload, $headers, 'geniuspay');

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

// ─── SÉCURITÉ MÉTIER ─────────────────────────────────────────────────────

it('should prevent user from verifying another users payment', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    Payment::factory()->pending()->create([
        'transaction_id' => 'KH-NOTMINE',
        'user_id' => $owner->id,
        'gateway' => 'geniuspay',
    ]);

    $response = $this->actingAs($intruder)
        ->postJson('/api/v1/payments/verify_payment', ['tx_ref' => 'KH-NOTMINE']);

    $response->assertNotFound();
});
