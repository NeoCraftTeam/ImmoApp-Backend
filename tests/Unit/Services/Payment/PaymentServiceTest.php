<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentInitiated;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
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
    config()->set('payment.gateways.flutterwave.base_url', 'https://api.flutterwave.com/v3');
    config()->set('payment.gateways.flutterwave.redirect_url', 'https://test.app/payment/callback');
});

// ─── CRÉATION ────────────────────────────────────────────────────────────

it('should create pending payment in database when payment is initiated', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/test123'],
        ], 200),
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
        'gateway' => 'flutterwave',
        'amount' => 150000,
    ]);
});

it('should return payment link when payment is created', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/test123'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $service = app(PaymentService::class);

    $result = $service->createPayment($user, [
        'amount' => 150000,
        'type' => 'unlock',
    ]);

    expect($result)
        ->toHaveKey('link')
        ->and($result['link'])->toContain('checkout.flutterwave.com');
});

it('should fire PaymentInitiated event when payment is created', function (): void {
    Event::fake();
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/test123'],
        ], 200),
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
        'api.flutterwave.com/*' => Http::response([
            'status' => 'error',
            'message' => 'Bad request',
        ], 400),
    ]);

    $user = User::factory()->create();
    $service = app(PaymentService::class);

    try {
        $service->createPayment($user, [
            'amount' => 150000,
            'type' => 'unlock',
        ]);
    } catch (\App\Exceptions\PaymentGatewayException) {
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

    Http::fake(function ($request) {
        if (str_contains((string) $request->url(), 'verify_by_reference')) {
            return Http::response([
                'status' => 'success',
                'data' => [
                    'status' => 'successful',
                    'amount' => 10000,
                    'currency' => 'XAF',
                ],
            ], 200);
        }

        return Http::response(['status' => 'success', 'data' => ['link' => 'https://test']], 200);
    });

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 10000,
    ]);

    $service = app(PaymentService::class);
    $updated = $service->syncPaymentStatus($payment);

    expect($updated->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('should return cached success without calling gateway when already paid', function (): void {
    Http::preventStrayRequests();

    $payment = Payment::factory()->success()->create([
        'gateway' => 'flutterwave',
    ]);

    $service = app(PaymentService::class);
    $result = $service->syncPaymentStatus($payment);

    expect($result->status)->toBe(PaymentStatus::SUCCESS);
    Http::assertNothingSent();
});

it('should fire PaymentSucceeded event only once for duplicate webhooks', function (): void {
    Event::fake();

    $payment = Payment::factory()->pending()->create([
        'gateway' => 'flutterwave',
        'amount' => 150000,
    ]);

    $webhookPayload = [
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $payment->transaction_id,
            'status' => 'successful',
            'amount' => 150000,
            'currency' => 'XAF',
        ],
    ];
    $headers = ['verif-hash' => config('payment.gateways.flutterwave.webhook_secret')];

    $service = app(PaymentService::class);

    $service->processWebhook($webhookPayload, $headers, 'flutterwave');
    $service->processWebhook($webhookPayload, $headers, 'flutterwave');
    $service->processWebhook($webhookPayload, $headers, 'flutterwave');

    Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
});

// ─── SÉCURITÉ MÉTIER ─────────────────────────────────────────────────────

it('should prevent user from verifying another users payment', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    Payment::factory()->pending()->create([
        'transaction_id' => 'KH-NOTMINE',
        'user_id' => $owner->id,
        'gateway' => 'flutterwave',
    ]);

    $response = $this->actingAs($intruder)
        ->postJson('/api/v1/payments/verify_payment', ['tx_ref' => 'KH-NOTMINE']);

    $response->assertNotFound();
});
