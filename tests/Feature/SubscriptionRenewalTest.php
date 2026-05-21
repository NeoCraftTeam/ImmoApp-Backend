<?php

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.redirect_url', 'https://test.app/payment/callback');

    $this->plan = SubscriptionPlan::create([
        'name' => 'Premium',
        'slug' => 'premium-renewal-test',
        'description' => 'Plan premium test',
        'price' => 35000,
        'price_yearly' => 350000,
        'duration_days' => 30,
        'boost_score' => 25,
        'boost_duration_days' => 14,
        'max_ads' => 50,
        'features' => ['Feature 1'],
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

it('toggles auto-renew on for active subscription', function (): void {
    $subscription = Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'auto_renew' => false,
    ]);

    Sanctum::actingAs($this->agentUser);

    $response = $this->patchJson('/api/v1/subscriptions/auto-renew');

    $response->assertSuccessful();
    $response->assertJsonPath('auto_renew', true);

    $subscription->refresh();
    expect($subscription->auto_renew)->toBeTrue();
});

it('toggles auto-renew off when already on', function (): void {
    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'auto_renew' => true,
    ]);

    Sanctum::actingAs($this->agentUser);

    $response = $this->patchJson('/api/v1/subscriptions/auto-renew');

    $response->assertSuccessful();
    $response->assertJsonPath('auto_renew', false);
});

it('returns 404 when toggling auto-renew without active subscription', function (): void {
    Sanctum::actingAs($this->agentUser);

    $response = $this->patchJson('/api/v1/subscriptions/auto-renew');

    $response->assertNotFound();
});

it('returns 403 when user has no agency', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $response = $this->patchJson('/api/v1/subscriptions/auto-renew');

    $response->assertForbidden();
});

it('sends renewal reminder for auto-renew subscriptions expiring in 3 days', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'checkout_url' => 'https://pay.genius.ci/checkout/renewal-123',
                'reference' => 'MTX-RENEWAL-123',
            ],
        ]),
    ]);

    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now()->subDays(27),
        'ends_at' => now()->addDays(3),
        'auto_renew' => true,
    ]);

    $service = app(SubscriptionService::class);
    $count = $service->processRenewals();

    expect($count)->toBe(1);

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($mail) => $mail->paymentUrl === 'https://pay.genius.ci/checkout/renewal-123');
});

it('does not send renewal for subscriptions without auto-renew', function (): void {
    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now()->subDays(27),
        'ends_at' => now()->addDays(3),
        'auto_renew' => false,
    ]);

    $service = app(SubscriptionService::class);
    $count = $service->processRenewals();

    expect($count)->toBe(0);
    Mail::assertNotQueued(SubscriptionRenewalReminderMail::class);
});

it('grants grace period for auto-renew subscriptions past expiry', function (): void {
    $subscription = Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now()->subDays(31),
        'ends_at' => now()->subDays(1),
        'auto_renew' => true,
    ]);

    $service = app(SubscriptionService::class);
    $expiredCount = $service->expireSubscriptions();

    expect($expiredCount)->toBe(0);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::ACTIVE);
});

it('expires auto-renew subscriptions after grace period', function (): void {
    $subscription = Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now()->subDays(34),
        'ends_at' => now()->subDays(4),
        'auto_renew' => true,
    ]);

    $service = app(SubscriptionService::class);
    $expiredCount = $service->expireSubscriptions();

    expect($expiredCount)->toBe(1);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::EXPIRED);
});

it('expires non-auto-renew subscriptions immediately', function (): void {
    $subscription = Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now()->subDays(31),
        'ends_at' => now()->subHour(),
        'auto_renew' => false,
    ]);

    $service = app(SubscriptionService::class);
    $expiredCount = $service->expireSubscriptions();

    expect($expiredCount)->toBe(1);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::EXPIRED);
});

it('creates a payment record for renewal', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'checkout_url' => 'https://pay.genius.ci/checkout/renewal-456',
                'reference' => 'MTX-RENEWAL-456',
            ],
        ]),
    ]);

    Subscription::create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'billing_period' => 'monthly',
        'status' => SubscriptionStatus::ACTIVE,
        'amount_paid' => 35000,
        'starts_at' => now()->subDays(27),
        'ends_at' => now()->addDays(3),
        'auto_renew' => true,
    ]);

    $service = app(SubscriptionService::class);
    $service->processRenewals();

    $this->assertDatabaseHas('payments', [
        'user_id' => $this->agentUser->id,
        'agency_id' => $this->agency->id,
        'plan_id' => $this->plan->id,
        'type' => 'subscription',
    ]);
});
