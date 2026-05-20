<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use App\Models\PointPackage;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.geniuspay.redirect_url', 'https://test.app/payment/callback');
});

it('rejects credit initiate without turnstile when turnstile is configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['turnstile_token']);
});

it('allows credit initiate without turnstile when turnstile is not configured', function (): void {
    config()->set('services.turnstile.secret_key', '');

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-TURNSTILE',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-TURNSTILE',
            ],
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
        ])
        ->assertSuccessful();
});

it('does not require turnstile for subscription initiate when turnstile is configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-TURNSTILE',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-TURNSTILE',
            ],
        ], 201),
    ]);

    $agency = Agency::factory()->create();
    $agentUser = User::factory()->create([
        'role' => UserRole::AGENT,
        'type' => UserType::AGENCY,
        'agency_id' => $agency->id,
    ]);

    /** @var SubscriptionPlan $plan */
    $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

    $this->actingAs($agentUser)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'subscription',
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'period' => 'monthly',
            'payment_method' => 'mobile_money',
            'phone_number' => '+237699000000',
        ])
        ->assertSuccessful();
});

it('passes credit initiate with valid turnstile token when turnstile is configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-TURNSTILE',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-TURNSTILE',
            ],
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
            'turnstile_token' => 'test-token-from-widget',
        ])
        ->assertSuccessful();
});

it('rejects credits purchase endpoint without turnstile when configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['turnstile_token']);
});

it('allows credits purchase endpoint with valid turnstile token when configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-TURNSTILE',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-TURNSTILE',
            ],
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'turnstile_token' => 'test-token-from-widget',
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['payment_url', 'tx_ref', 'gateway']);
});
