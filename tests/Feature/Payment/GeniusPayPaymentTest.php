<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Events\PaymentInitiated;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
