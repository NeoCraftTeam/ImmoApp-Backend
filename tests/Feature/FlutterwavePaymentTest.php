<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentInitiated;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function flwConfig(): void
{
    config()->set('payment.default', 'flutterwave');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'flw-test-secret');
    config()->set('payment.gateways.flutterwave.redirect_url', 'https://test.app/payment/callback');
}

// -- INITIATE ----------------------------------------------------------------

it('authenticated user can initiate a Flutterwave payment', function (): void {
    Event::fake();
    flwConfig();

    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);

    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'message' => 'Hosted Link',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/test123'],
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237650000000',
        'plan_id' => $package->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['reference', 'payment_link', 'tx_ref', 'gateway', 'status'])
        ->assertJsonPath('gateway', 'flutterwave')
        ->assertJsonPath('status', 'pending');

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'type' => 'credit',
        'status' => PaymentStatus::PENDING->value,
    ]);

    Event::assertDispatched(PaymentInitiated::class);
});

it('guest cannot initiate a Flutterwave payment', function (): void {
    $this->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
    ])->assertUnauthorized();
});

it('validates required fields on initiate', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('rejects invalid payment type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', ['type' => 'bad_type'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

// -- VERIFY ------------------------------------------------------------------

it('verify returns success when Flutterwave confirms payment', function (): void {
    Event::fake();
    flwConfig();

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-TESTVERIFY001',
        'status' => PaymentStatus::PENDING,
        'type' => 'boost',
        'payment_method' => 'mobile_money',
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 10000,
    ]);

    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'successful',
                'amount' => 10000,
                'currency' => 'XAF',
                'payment_type' => 'mobilemoneygh',
                'charged_amount' => 10000,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/verify_payment', [
        'tx_ref' => 'KH-TESTVERIFY001',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('is_paid', true);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    Event::assertDispatched(PaymentSucceeded::class);
});

it('verify returns 404 when tx_ref belongs to a different user', function (): void {
    flwConfig();

    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    Payment::factory()->create([
        'transaction_id' => 'KH-NOTMINE',
        'status' => PaymentStatus::PENDING,
        'user_id' => $owner->id,
        'gateway' => 'flutterwave',
    ]);

    $this->actingAs($intruder)
        ->postJson('/api/v1/payments/verify_payment', ['tx_ref' => 'KH-NOTMINE'])
        ->assertNotFound();
});

it('verify is idempotent: already-success payment skips API call', function (): void {
    Event::fake();
    flwConfig();

    $user = User::factory()->create();
    Payment::factory()->create([
        'transaction_id' => 'KH-ALREADY-DONE',
        'status' => PaymentStatus::SUCCESS,
        'type' => 'boost',
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 5000,
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/payments/verify_payment', ['tx_ref' => 'KH-ALREADY-DONE'])
        ->assertSuccessful();

    Http::assertNothingSent();
    Event::assertNotDispatched(PaymentSucceeded::class);
});

// -- HISTORY -----------------------------------------------------------------

it('authenticated user only sees their own payment history', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Payment::factory()->count(3)->create(['user_id' => $user->id,  'gateway' => 'flutterwave']);
    Payment::factory()->count(2)->create(['user_id' => $other->id, 'gateway' => 'flutterwave']);

    $this->actingAs($user)
        ->getJson('/api/v1/payments/history')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('guest cannot access payment history', function (): void {
    $this->getJson('/api/v1/payments/history')->assertUnauthorized();
});
