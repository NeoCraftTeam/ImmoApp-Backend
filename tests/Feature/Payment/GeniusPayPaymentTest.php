<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Events\PaymentInitiated;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function geniusPayFeatureConfig(): void
{
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.geniuspay.redirect_url', 'https://test.app/payment/callback');
}

it('authenticated user can initiate a GeniusPay payment', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-FEATURE-001',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-FEATURE-001',
                'status' => 'pending',
            ],
        ], 201),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237650000000',
        'plan_id' => $package->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['reference', 'payment_link', 'tx_ref', 'gateway', 'status'])
        ->assertJsonPath('gateway', 'geniuspay')
        ->assertJsonPath('status', 'pending');

    Http::assertSent(fn (Request $request) => $request->data()['currency'] === 'XOF');

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'type' => 'credit',
        'status' => PaymentStatus::PENDING->value,
        'gateway' => 'geniuspay',
    ]);

    Event::assertDispatched(PaymentInitiated::class);
});

it('stores genius reference in gateway_response for verify', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 2500, 'is_active' => true]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-VERIFY-REF',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-VERIFY-REF',
            ],
        ], 201),
    ]);

    $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'orange_money',
        'phone_number' => '+237650000001',
        'plan_id' => $package->id,
    ])->assertSuccessful();

    $payment = Payment::query()->where('user_id', $user->id)->latest()->first();
    expect($payment)->not->toBeNull()
        ->and($payment->gateway_response)->toBeArray()
        ->and($payment->gateway_response['genius_reference'] ?? null)->toBe('MTX-VERIFY-REF');
});

it('verifies payment by genius reference on verify_payment endpoint', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'gateway_response' => ['genius_reference' => 'SANDBOX_VERIFY_ENDPOINT'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX_VERIFY_ENDPOINT',
                'status' => 'completed',
                'amount' => 5000,
                'currency' => 'XAF',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/verify_payment', [
        'reference' => 'SANDBOX_VERIFY_ENDPOINT',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('is_paid', true);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);
});

it('verifies payment by tx_ref and genius reference together on verify_payment', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'transaction_id' => 'KH-3X2HK4FMW3VR',
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'gateway_response' => ['genius_reference' => 'SANDBOX_R2YUJPRMF62CQXYI'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
                'status' => 'completed',
                'amount' => 5000,
                'currency' => 'XAF',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/verify_payment', [
        'tx_ref' => 'KH-3X2HK4FMW3VR',
        'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('is_paid', true)
        ->assertJsonPath('tx_ref', 'KH-3X2HK4FMW3VR');

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);
});

it('accepts geniuspay verify when gateway returns XOF for XAF ledger row', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'gateway_response' => ['genius_reference' => 'SANDBOX_XOF_VERIFY'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX_XOF_VERIFY',
                'status' => 'completed',
                'amount' => 5000,
                'currency' => 'XOF',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/verify_payment', [
        'tx_ref' => $payment->transaction_id,
        'reference' => 'SANDBOX_XOF_VERIFY',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('is_paid', true);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);
});

it('reopens a locally FAILED geniuspay payment when verify confirms completed', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => PaymentStatus::FAILED,
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'transaction_id' => 'KH-3X2HK4FMW3VR',
        'gateway_response' => ['genius_reference' => 'SANDBOX_R2YUJPRMF62CQXYI'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
                'status' => 'completed',
                'amount' => 5000,
                'currency' => 'XOF',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/verify_payment', [
        'tx_ref' => 'KH-3X2HK4FMW3VR',
        'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('is_paid', true);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);
});

it('uses redirect sandbox reference for verify when stored reference differs', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 3000,
        'transaction_id' => 'KH-SANDBOXREDIRECT',
        'gateway_response' => ['genius_reference' => 'MTX-OLD-REF'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => function (Request $request) {
            expect($request->url())->toContain('SANDBOX_R2YUJPRMF62CQXYI');

            return Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
                    'status' => 'completed',
                    'amount' => 3000,
                    'currency' => 'XOF',
                ],
            ], 200);
        },
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/verify_payment', [
        'tx_ref' => 'KH-SANDBOXREDIRECT',
        'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('is_paid', true);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);
});

it('reopens FAILED credit purchase via verify-purchase when geniuspay completed', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create(['points_awarded' => 50, 'price' => 5000, 'is_active' => true]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'type' => 'credit',
        'plan_id' => $package->id,
        'status' => PaymentStatus::FAILED,
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'transaction_id' => 'KH-CREDREOPEN01',
        'gateway_response' => ['genius_reference' => 'SANDBOX_CREDIT_REOPEN'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX_CREDIT_REOPEN',
                'status' => 'completed',
                'amount' => 5000,
                'currency' => 'XOF',
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase', [
            'tx_ref' => 'KH-CREDREOPEN01',
            'reference' => 'SANDBOX_CREDIT_REOPEN',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);
});

it('verify-purchase syncs sandbox credit payment when redirect reference differs from stored ref', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 3000, 'points_awarded' => 10, 'is_active' => true]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'type' => PaymentType::CREDIT,
        'plan_id' => $package->id,
        'gateway' => 'geniuspay',
        'amount' => 3000,
        'transaction_id' => 'KH-3X2HK4FMW3VR',
        'gateway_response' => ['genius_reference' => 'MTX-OLD-REF'],
    ]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
                'status' => 'completed',
                'amount' => 3000,
                'currency' => 'XOF',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/credits/verify-purchase', [
        'tx_ref' => 'KH-3X2HK4FMW3VR',
        'reference' => 'SANDBOX_R2YUJPRMF62CQXYI',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'completed');

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);
    expect($user->fresh()?->point_balance)->toBeGreaterThan(0);
});

it('returns 422 with french message when geniuspay rejects payload', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'validation.in',
                'errors' => ['currency' => ['validation.in']],
            ],
        ], 422),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237650000000',
        'plan_id' => $package->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'PAYMENT_VALIDATION_ERROR');

    $message = (string) $response->json('message');
    expect($message)->toContain('devise')
        ->and($message)->not->toContain('validation.in');
});

it('verify is idempotent: already-success payment skips gateway API call', function (): void {
    Event::fake();
    Http::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create();
    Payment::factory()->create([
        'transaction_id' => 'KH-ALREADYDONE1',
        'status' => PaymentStatus::SUCCESS,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'gateway_response' => ['genius_reference' => 'MTX-ALREADY'],
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/payments/verify_payment', ['tx_ref' => 'KH-ALREADYDONE1'])
        ->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(PaymentSucceeded::class);
});

/**
 * Regression: client-supplied `gateway_redirect_status=completed` MUST NOT
 * force a pending credit purchase to SUCCESS when the gateway verify API
 * still reports pending. Prior to the fix, that flow let any authenticated
 * user mint free credits by creating a payment and immediately calling
 * verify-purchase with a forged redirect hint, never actually paying.
 */
it('rejects free-credit bypass via gateway_redirect_status hint', function (): void {
    Event::fake();
    geniusPayFeatureConfig();

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create(['price' => 5000, 'points_awarded' => 50, 'is_active' => true]);

    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'type' => PaymentType::CREDIT,
        'plan_id' => $package->id,
        'gateway' => 'geniuspay',
        'amount' => 5000,
        'transaction_id' => 'KH-BYPASSATTEMPT01',
        'payment_link' => 'https://pay.genius.ci/checkout/MTX-BYPASS',
        'gateway_response' => ['genius_reference' => 'MTX-BYPASS'],
    ]);

    // Gateway verify API is silent (sandbox returns "transaction not found"
    // shape → mapped to pending by the gateway service).
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => false,
            'data' => ['reference' => 'MTX-BYPASS', 'status' => 'pending'],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase', [
            'tx_ref' => 'KH-BYPASSATTEMPT01',
            'reference' => 'MTX-BYPASS',
            // The malicious hint: prior code would honour this and force SUCCESS.
            'gateway_redirect_status' => 'completed',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('point_balance', 0);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::PENDING);
    expect($user->fresh()?->point_balance)->toBe(0);
    Event::assertNotDispatched(PaymentSucceeded::class);
});
