<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('can renew expired subscription', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create(['duration_days' => 30]);
    $subscription = Subscription::factory()->expired()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/subscriptions/renew');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'subscription',
        ]);

    expect($subscription->fresh())
        ->status->toBe(SubscriptionStatus::ACTIVE)
        ->renewed_at->not->toBeNull()
        ->renewal_count->toBe(1);
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

it('can upgrade subscription', function (): void {
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

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'subscription',
        ]);

    expect($subscription->fresh())
        ->subscription_plan_id->toBe($premiumPlan->id)
        ->previous_plan_id->toBe($basicPlan->id);
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
