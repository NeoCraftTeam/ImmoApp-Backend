<?php

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\FedaPayService;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->plan = SubscriptionPlan::create([
        'name' => 'Premium',
        'slug' => 'premium-test',
        'description' => 'Plan premium test',
        'price' => 35000,
        'price_yearly' => 350000,
        'duration_days' => 30,
        'boost_score' => 25,
        'boost_duration_days' => 14,
        'max_ads' => 50,
        'features' => ['Feature 1', 'Feature 2'],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->agency = Agency::factory()->create();
    $this->agentUser = User::factory()->create([
        'role' => UserRole::AGENT,
        'type' => UserType::AGENCY,
        'agency_id' => $this->agency->id,
    ]);
});

test('anyone can list active subscription plans', function (): void {
    $inactivePlan = SubscriptionPlan::create([
        'name' => 'Inactive',
        'slug' => 'inactive-test',
        'price' => 5000,
        'duration_days' => 30,
        'boost_score' => 0,
        'boost_duration_days' => 0,
        'is_active' => false,
        'sort_order' => 99,
    ]);

    $response = $this->getJson('/api/v1/subscriptions/plans');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Premium'])
        ->assertJsonMissing(['name' => 'Inactive']);
});

test('plans response includes yearly pricing and savings', function (): void {
    $response = $this->getJson('/api/v1/subscriptions/plans');

    $response->assertSuccessful();
    $plan = $response->json('data.0');

    expect($plan)
        ->toHaveKey('price_monthly', 35000)
        ->toHaveKey('price_yearly', 350000)
        ->toHaveKey('yearly_savings', 70000)
        ->toHaveKey('features');
});

test('unauthenticated user cannot access current subscription', function (): void {
    $response = $this->getJson('/api/v1/subscriptions/current');

    $response->assertUnauthorized();
});

test('agent can view current subscription (no active)', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->getJson('/api/v1/subscriptions/current');

    $response->assertSuccessful()
        ->assertJson([
            'has_subscription' => false,
            'subscription' => null,
        ]);
});

test('agent can view active subscription', function (): void {
    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($this->agentUser);

    $response = $this->getJson('/api/v1/subscriptions/current');

    $response->assertSuccessful()
        ->assertJson(['has_subscription' => true])
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.is_active', true);
});

test('user without agency cannot subscribe', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
    ]);

    $response->assertForbidden();
});

test('agent can initiate subscription payment', function (): void {
    $mock = Mockery::mock(FedaPayService::class);
    $mock->shouldReceive('createSubscriptionPayment')
        ->once()
        ->withArgs(function ($amount, $agency, $planId, $period) {
            return $amount === 35000 && $period === 'monthly';
        })
        ->andReturn([
            'success' => true,
            'url' => 'https://fedapay.com/pay/sub-123',
            'transaction_id' => 'sub-tx-123',
        ]);
    $this->app->instance(FedaPayService::class, $mock);

    Sanctum::actingAs($this->agentUser);

    $response = $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['payment_url', 'message']);

    $this->assertDatabaseHas('payments', [
        'user_id' => $this->agentUser->id,
        'agency_id' => $this->agency->id,
        'plan_id' => $this->plan->id,
        'period' => 'monthly',
    ]);
});

test('agent can subscribe yearly with correct amount', function (): void {
    $mock = Mockery::mock(FedaPayService::class);
    $mock->shouldReceive('createSubscriptionPayment')
        ->once()
        ->withArgs(function ($amount) {
            return $amount === 350000;
        })
        ->andReturn([
            'success' => true,
            'url' => 'https://fedapay.com/pay/sub-yearly',
            'transaction_id' => 'sub-tx-yearly',
        ]);
    $this->app->instance(FedaPayService::class, $mock);

    Sanctum::actingAs($this->agentUser);

    $response = $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_id' => $this->plan->id,
        'billing_period' => 'yearly',
    ]);

    $response->assertSuccessful();
});

test('subscribe request validation fails with invalid data', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_id' => 'not-a-uuid',
        'billing_period' => 'weekly',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id', 'billing_period']);
});

test('agent can cancel active subscription', function (): void {
    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($this->agentUser);

    $response = $this->postJson('/api/v1/subscriptions/cancel', [
        'reason' => 'Je n\'en ai plus besoin',
    ]);

    $response->assertSuccessful()
        ->assertJsonFragment(['status' => 'cancelled']);
});

test('cancel returns 404 if no active subscription', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->postJson('/api/v1/subscriptions/cancel');

    $response->assertNotFound();
});

test('agent can view subscription history', function (): void {
    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::EXPIRED,
        'amount_paid' => 35000,
        'starts_at' => now()->subDays(60),
        'ends_at' => now()->subDays(30),
    ]);

    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
    ]);

    Sanctum::actingAs($this->agentUser);

    $response = $this->getJson('/api/v1/subscriptions/history');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
