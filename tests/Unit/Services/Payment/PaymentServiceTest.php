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
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.kpay.base_url', 'https://admin.kpay.site');
    config()->set('payment.gateways.kpay.redirect_url', 'https://test.app/payment/callback');
});

// ─── CRÉATION ────────────────────────────────────────────────────────────

it('should create pending payment in database when payment is initiated', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_SVC_001',
            'reference' => 'KPAY-MTX-SVC-001',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_SVC_001',
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
        'gateway' => 'kpay',
        'amount' => 150000,
    ]);

    Http::assertSent(function (Request $request) use ($result): bool {
        $returnUrl = (string) ($request->data()['returnUrl'] ?? '');

        return str_contains($returnUrl, 'tx_ref='.urlencode($result['tx_ref']));
    });
});

it('should return payment link when payment is created', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_SVC_001',
            'reference' => 'KPAY-MTX-SVC-001',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_SVC_001',
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
        ->and($result['link'])->toContain('admin.kpay.site/gateway');
});

it('should fire PaymentInitiated event when payment is created', function (): void {
    Event::fake();
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_SVC_001',
            'reference' => 'KPAY-MTX-SVC-001',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_SVC_001',
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
        'admin.kpay.site/*' => Http::response([
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
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_VERIFY_OK',
            'reference' => 'KPAY-MTX-VERIFY-OK',
            'status' => 'COMPLETED',
            'amount' => 10000,
            'currency' => 'XAF',
        ], 200),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 10000,
        'gateway_response' => ['kpay_id' => 'pay_MTX_VERIFY_OK'],
    ]);

    $service = app(PaymentService::class);
    $updated = $service->syncPaymentStatus($payment);

    expect($updated->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('reopens FAILED kpay payment when sync confirms completed', function (): void {
    Event::fake();

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_SANDBOX_REOPEN_001',
            'reference' => 'KPAY-SANDBOX-REOPEN-001',
            'status' => 'COMPLETED',
            'amount' => 10000,
            'currency' => 'XOF',
        ], 200),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => PaymentStatus::FAILED,
        'gateway' => 'kpay',
        'amount' => 10000,
        'gateway_response' => ['kpay_id' => 'pay_SANDBOX_REOPEN_001'],
    ]);

    $service = app(PaymentService::class);
    $updated = $service->syncPaymentStatus($payment);

    expect($updated->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('should return cached success without calling gateway when already paid', function (): void {
    Http::preventStrayRequests();

    $payment = Payment::factory()->success()->create([
        'gateway' => 'kpay',
    ]);

    $service = app(PaymentService::class);
    $result = $service->syncPaymentStatus($payment);

    expect($result->status)->toBe(PaymentStatus::SUCCESS);
    Http::assertNothingSent();
});

it('should fire PaymentSucceeded event only once for duplicate webhooks', function (): void {
    Event::fake();

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'kpay',
        'amount' => 150000,
    ]);

    $secret = config('payment.gateways.kpay.webhook_secret');
    $webhookPayload = [
        'event' => 'payment.completed',
        'paymentId' => 'pay_dup_001',
        'reference' => 'KPAY-DUP-001',
        'status' => 'COMPLETED',
        'amount' => 150000,
        'currency' => 'XAF',
        'externalId' => $payment->transaction_id,
    ];
    $rawBody = json_encode($webhookPayload, JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', (string) $rawBody, (string) $secret);
    $headers = [
        'X-KPAY-Signature' => $signature,
        'X-KPAY-Event' => 'payment.completed',
    ];

    $service = app(PaymentService::class);

    $service->processWebhook($webhookPayload, $headers, 'kpay', (string) $rawBody);
    $service->processWebhook($webhookPayload, $headers, 'kpay', (string) $rawBody);
    $service->processWebhook($webhookPayload, $headers, 'kpay', (string) $rawBody);

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

// ─── THROTTLE VÉRIFICATION ───────────────────────────────────────────────

it('coalesces repeated client polls into a single gateway verify call', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_THROTTLE_A',
            'reference' => 'KPAY-THROTTLE-A',
            'status' => 'PENDING',
            'amount' => 10000,
            'currency' => 'XAF',
        ], 200),
    ]);

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'kpay',
        'amount' => 10000,
        'gateway_response' => ['kpay_id' => 'pay_THROTTLE_A'],
    ]);

    $service = app(PaymentService::class);

    // Three near-simultaneous polls for the same transaction: only the first
    // reaches the gateway, the rest are served from the throttle window.
    $service->syncPaymentStatus($payment, useVerifyThrottle: true);
    $service->syncPaymentStatus($payment, useVerifyThrottle: true);
    $result = $service->syncPaymentStatus($payment, useVerifyThrottle: true);

    expect($result->status)->toBe(PaymentStatus::PENDING);
    Http::assertSentCount(1);
});

it('always hits the gateway when the verify throttle is not opted in', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_THROTTLE_B',
            'reference' => 'KPAY-THROTTLE-B',
            'status' => 'PENDING',
            'amount' => 10000,
            'currency' => 'XAF',
        ], 200),
    ]);

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'kpay',
        'amount' => 10000,
        'gateway_response' => ['kpay_id' => 'pay_THROTTLE_B'],
    ]);

    $service = app(PaymentService::class);

    // Reconciliation cron / Filament path: every call must reach the gateway.
    $service->syncPaymentStatus($payment);
    $service->syncPaymentStatus($payment);

    Http::assertSentCount(2);
});

it('verifies again once the throttle window has elapsed', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_THROTTLE_C',
            'reference' => 'KPAY-THROTTLE-C',
            'status' => 'PENDING',
            'amount' => 10000,
            'currency' => 'XAF',
        ], 200),
    ]);

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'kpay',
        'amount' => 10000,
        'gateway_response' => ['kpay_id' => 'pay_THROTTLE_C'],
    ]);

    $service = app(PaymentService::class);

    $service->syncPaymentStatus($payment, useVerifyThrottle: true);
    $this->travel(6)->seconds();
    $service->syncPaymentStatus($payment, useVerifyThrottle: true);

    Http::assertSentCount(2);
});

it('surfaces a webhook-driven success even while a poll is throttled', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_THROTTLE_D',
            'reference' => 'KPAY-THROTTLE-D',
            'status' => 'PENDING',
            'amount' => 10000,
            'currency' => 'XAF',
        ], 200),
    ]);

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'kpay',
        'amount' => 10000,
        'gateway_response' => ['kpay_id' => 'pay_THROTTLE_D'],
    ]);

    $service = app(PaymentService::class);

    // First poll arms the throttle window (gateway still says PENDING).
    $service->syncPaymentStatus($payment, useVerifyThrottle: true);

    // The signed webhook lands out-of-band and promotes the row in the DB
    // without touching our in-memory instance.
    Payment::query()->whereKey($payment->id)->update([
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    // A throttled poll must still reflect the fresh DB state, never mask it.
    $result = $service->syncPaymentStatus($payment, useVerifyThrottle: true);

    expect($result->status)->toBe(PaymentStatus::SUCCESS);
    Http::assertSentCount(1);
});

// ─── SÉCURITÉ MÉTIER ─────────────────────────────────────────────────────

it('should prevent user from verifying another users payment', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    Payment::factory()->pending()->create([
        'transaction_id' => 'KH-NOTMINE',
        'user_id' => $owner->id,
        'gateway' => 'kpay',
    ]);

    $response = $this->actingAs($intruder)
        ->postJson('/api/v1/payments/verify_payment', ['tx_ref' => 'KH-NOTMINE']);

    $response->assertNotFound();
});
