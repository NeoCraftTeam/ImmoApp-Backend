<?php

declare(strict_types=1);

use App\Actions\HandlePostPaymentActions;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Stub the GeniusPay gateway so subscribe()/upgrade()/renew() can produce a
 * checkout link without hitting the network. Tests for the state-mutation
 * contract should not depend on real gateway connectivity.
 */
function stubGeniusPayCheckout(): void
{
    config()->set('payment.default', 'geniuspay');
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.geniuspay.redirect_url', 'https://test.app/payment/callback');

    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SUB-TEST',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-SUB-TEST',
                'status' => 'pending',
            ],
        ], 201),
    ]);
}

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->agency->update(['owner_id' => $this->user->id]);
});

it('can list subscription plans', function (): void {
    SubscriptionPlan::factory()->basic()->create();
    SubscriptionPlan::factory()->premium()->create();
    SubscriptionPlan::factory()->enterprise()->create();

    $response = $this->getJson('/api/v1/subscriptions/plans');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'price_monthly',
                    'price_yearly',
                    'duration_days',
                    'boost_score',
                    'max_ads',
                    'features',
                ],
            ],
        ]);
});

it('can get current subscription', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();
    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/subscriptions/current');

    $response->assertSuccessful()
        ->assertJson([
            'has_subscription' => true,
        ])
        ->assertJsonStructure([
            'has_subscription',
            'subscription' => [
                'id',
                'plan',
                'billing_period',
                'status',
                'amount_paid',
                'starts_at',
                'ends_at',
                'is_active',
            ],
        ]);
});

it('returns false when no current subscription', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/subscriptions/current');

    $response->assertSuccessful()
        ->assertJson([
            'has_subscription' => false,
            'subscription' => null,
        ]);
});

it('can cancel subscription', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();
    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/subscriptions/cancel', [
            'reason' => 'Test cancellation reason',
        ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'subscription',
        ]);

    expect($subscription->fresh())
        ->cancelled_at->not->toBeNull()
        ->cancellation_reason->toBe('Test cancellation reason')
        ->auto_renew->toBeFalse();
});

it('can toggle auto-renew', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();
    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
        'auto_renew' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson('/api/v1/subscriptions/auto-renew');

    $response->assertSuccessful()
        ->assertJson([
            'auto_renew' => true,
        ]);

    expect($subscription->fresh()->auto_renew)->toBeTrue();
});

it('can view subscription history', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();

    Subscription::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/subscriptions/history');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'plan',
                    'billing_period',
                    'status',
                    'amount_paid',
                    'is_active',
                ],
            ],
            'links',
            'meta',
        ]);
});

it('renew creates a payment and does not mutate state until webhook succeeds', function (): void {
    stubGeniusPayCheckout();

    $plan = SubscriptionPlan::factory()->premium()->create(['duration_days' => 30]);
    $subscription = Subscription::factory()->expired()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/subscriptions/renew');

    $response->assertStatus(202)
        ->assertJsonStructure(['payment_url', 'message']);

    // State must NOT have advanced — the old build called Subscription::renew()
    // inline here for free; the new build requires a signed webhook.
    expect($subscription->fresh())
        ->status->toBe(SubscriptionStatus::EXPIRED)
        ->renewed_at->toBeNull()
        ->renewal_count->toBe(0);
});

it('cannot renew active subscription', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();
    Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/subscriptions/renew');

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'L\'abonnement est déjà actif.',
        ]);
});

it('upgrade creates a payment and does not flip plan until webhook succeeds', function (): void {
    stubGeniusPayCheckout();

    $basicPlan = SubscriptionPlan::factory()->basic()->create();
    $premiumPlan = SubscriptionPlan::factory()->premium()->create();

    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/subscriptions/upgrade', [
            'plan_id' => $premiumPlan->id,
            'billing_period' => 'monthly',
        ]);

    $response->assertStatus(202)
        ->assertJsonStructure(['payment_url', 'message']);

    // Plan must NOT flip on the inline call — the old build called
    // Subscription::upgradeTo() here for free, letting any agency member
    // mint a premium tier without paying. The mutation now happens only
    // from HandlePostPaymentActions on a verified webhook.
    expect($subscription->fresh())
        ->subscription_plan_id->toBe($basicPlan->id)
        ->previous_plan_id->toBeNull();
});

it('non-owner agency member cannot trigger upgrade / renew / downgrade / cancel', function (): void {
    $basicPlan = SubscriptionPlan::factory()->basic()->create();
    $premiumPlan = SubscriptionPlan::factory()->premium()->create();

    Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);

    // Second agency member — joined the agency, but is not the owner.
    $member = User::factory()->create(['agency_id' => $this->agency->id]);

    $upgrade = $this->actingAs($member)->postJson('/api/v1/subscriptions/upgrade', [
        'plan_id' => $premiumPlan->id,
        'billing_period' => 'monthly',
    ]);
    $upgrade->assertForbidden()
        ->assertJsonPath('message', 'Seul le propriétaire de l\'agence peut mettre à niveau l\'abonnement.');

    $renew = $this->actingAs($member)->postJson('/api/v1/subscriptions/renew');
    $renew->assertForbidden();

    $downgrade = $this->actingAs($member)->postJson('/api/v1/subscriptions/downgrade', [
        'plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);
    $downgrade->assertForbidden();

    $cancel = $this->actingAs($member)->postJson('/api/v1/subscriptions/cancel');
    $cancel->assertForbidden();
});

it('can downgrade subscription', function (): void {
    $premiumPlan = SubscriptionPlan::factory()->premium()->create();
    $basicPlan = SubscriptionPlan::factory()->basic()->create();

    $originalEndsAt = now()->addDays(20);

    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $premiumPlan->id,
        'billing_period' => 'yearly',
        'ends_at' => $originalEndsAt,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/subscriptions/downgrade', [
            'plan_id' => $basicPlan->id,
            'billing_period' => 'monthly',
        ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'subscription',
        ]);

    $refreshed = $subscription->fresh();

    expect($refreshed)
        ->subscription_plan_id->toBe($basicPlan->id)
        ->previous_plan_id->toBe($premiumPlan->id);

    expect($refreshed->ends_at->timestamp)->toBe($originalEndsAt->timestamp);
});

it('requires authentication for subscription actions', function (): void {
    $this->postJson('/api/v1/subscriptions/cancel')
        ->assertUnauthorized();

    $this->patchJson('/api/v1/subscriptions/auto-renew')
        ->assertUnauthorized();

    $this->getJson('/api/v1/subscriptions/history')
        ->assertUnauthorized();

    $this->postJson('/api/v1/subscriptions/renew')
        ->assertUnauthorized();

    $this->postJson('/api/v1/subscriptions/upgrade')
        ->assertUnauthorized();

    $this->postJson('/api/v1/subscriptions/downgrade')
        ->assertUnauthorized();
});

it('requires agency membership', function (): void {
    $userWithoutAgency = User::factory()->create(['agency_id' => null]);

    $this->actingAs($userWithoutAgency)
        ->getJson('/api/v1/subscriptions/current')
        ->assertForbidden()
        ->assertJson([
            'message' => 'Vous n\'appartenez à aucune agence.',
        ]);
});

it('post-payment fulfilment upgrades subscription only after webhook succeeds', function (): void {
    $basicPlan = SubscriptionPlan::factory()->basic()->create(['duration_days' => 30]);
    $premiumPlan = SubscriptionPlan::factory()->premium()->create(['duration_days' => 30]);

    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $this->user->id,
        'agency_id' => $this->agency->id,
        'plan_id' => $premiumPlan->id,
        'period' => 'monthly',
        'type' => PaymentType::SUBSCRIPTION,
        'status' => PaymentStatus::SUCCESS,
        'amount' => (int) $premiumPlan->price,
    ]);

    app(HandlePostPaymentActions::class)->execute($payment, [
        'meta' => [
            'action' => 'upgrade',
            'agency_id' => $this->agency->id,
            'plan_id' => $premiumPlan->id,
            'subscription_id' => $subscription->id,
            'period' => 'monthly',
        ],
    ]);

    expect($subscription->fresh())
        ->subscription_plan_id->toBe($premiumPlan->id)
        ->previous_plan_id->toBe($basicPlan->id);
});

it('post-payment fulfilment is idempotent for upgrade replays', function (): void {
    $basicPlan = SubscriptionPlan::factory()->basic()->create(['duration_days' => 30]);
    $premiumPlan = SubscriptionPlan::factory()->premium()->create(['duration_days' => 30]);

    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);

    $payment = Payment::factory()->create([
        'user_id' => $this->user->id,
        'agency_id' => $this->agency->id,
        'plan_id' => $premiumPlan->id,
        'period' => 'monthly',
        'type' => PaymentType::SUBSCRIPTION,
        'status' => PaymentStatus::SUCCESS,
        'amount' => (int) $premiumPlan->price,
    ]);

    $meta = [
        'meta' => [
            'action' => 'upgrade',
            'agency_id' => $this->agency->id,
            'plan_id' => $premiumPlan->id,
            'subscription_id' => $subscription->id,
            'period' => 'monthly',
        ],
    ];

    // Fire the same webhook twice — second call must no-op.
    app(HandlePostPaymentActions::class)->execute($payment, $meta);
    $afterFirst = $subscription->fresh();

    app(HandlePostPaymentActions::class)->execute($payment, $meta);
    $afterSecond = $subscription->fresh();

    expect($afterSecond->subscription_plan_id)->toBe($premiumPlan->id);
    // ends_at should not advance on the second call — that would create
    // a free extra period for any replayed webhook.
    expect($afterSecond->ends_at->timestamp)->toBe($afterFirst->ends_at->timestamp);
});
