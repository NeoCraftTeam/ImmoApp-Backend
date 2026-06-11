<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can check if subscription is on trial', function (): void {
    $subscription = Subscription::factory()
        ->onTrial(14)
        ->create();

    expect($subscription->isOnTrial())->toBeTrue();
});

it('returns false for isOnTrial when trial_ends_at is null', function (): void {
    $subscription = Subscription::factory()
        ->create(['trial_ends_at' => null]);

    expect($subscription->isOnTrial())->toBeFalse();
});

it('returns false for isOnTrial when trial has ended', function (): void {
    $subscription = Subscription::factory()
        ->trialEnded()
        ->create();

    expect($subscription->isOnTrial())->toBeFalse();
});

it('returns false for isOnTrial when subscription is not active', function (): void {
    $subscription = Subscription::factory()
        ->onTrial(14)
        ->create(['status' => SubscriptionStatus::CANCELLED]);

    expect($subscription->isOnTrial())->toBeFalse();
});

it('can check if trial has ended', function (): void {
    $subscription = Subscription::factory()
        ->trialEnded()
        ->create();

    expect($subscription->trialHasEnded())->toBeTrue();
});

it('returns false for trialHasEnded when trial_ends_at is null', function (): void {
    $subscription = Subscription::factory()
        ->create(['trial_ends_at' => null]);

    expect($subscription->trialHasEnded())->toBeFalse();
});

it('returns false for trialHasEnded when trial is still active', function (): void {
    $subscription = Subscription::factory()
        ->onTrial(14)
        ->create();

    expect($subscription->trialHasEnded())->toBeFalse();
});

it('calculates trial days remaining correctly', function (): void {
    $subscription = Subscription::factory()
        ->onTrial(14)
        ->create();

    $daysRemaining = $subscription->trialDaysRemaining();

    expect($daysRemaining)->toBeGreaterThanOrEqual(13)
        ->and($daysRemaining)->toBeLessThanOrEqual(14);
});

it('returns zero trial days remaining when not on trial', function (): void {
    $subscription = Subscription::factory()
        ->create(['trial_ends_at' => null]);

    expect($subscription->trialDaysRemaining())->toBe(0);
});

it('returns zero trial days remaining when trial has ended', function (): void {
    $subscription = Subscription::factory()
        ->trialEnded()
        ->create();

    expect($subscription->trialDaysRemaining())->toBe(0);
});

it('can activate subscription with trial', function (): void {
    $plan = SubscriptionPlan::factory()
        ->withTrial(14)
        ->create(['duration_days' => 30]);

    $subscription = Subscription::factory()
        ->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'status' => SubscriptionStatus::PENDING,
            'starts_at' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

    $subscription->activate(withTrial: true);

    expect($subscription->fresh())
        ->status->toBe(SubscriptionStatus::ACTIVE)
        ->starts_at->not->toBeNull()
        ->trial_ends_at->not->toBeNull()
        ->ends_at->not->toBeNull();

    expect($subscription->isOnTrial())->toBeTrue();
    expect($subscription->trialDaysRemaining())->toBeGreaterThanOrEqual(13)
        ->and($subscription->trialDaysRemaining())->toBeLessThanOrEqual(14);
});

it('can activate subscription without trial', function (): void {
    $plan = SubscriptionPlan::factory()
        ->withTrial(14)
        ->create(['duration_days' => 30]);

    $subscription = Subscription::factory()
        ->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'status' => SubscriptionStatus::PENDING,
            'starts_at' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

    $subscription->activate(withTrial: false);

    expect($subscription->fresh())
        ->status->toBe(SubscriptionStatus::ACTIVE)
        ->starts_at->not->toBeNull()
        ->trial_ends_at->toBeNull()
        ->ends_at->not->toBeNull();

    expect($subscription->isOnTrial())->toBeFalse();
});

it('activates with yearly billing period correctly', function (): void {
    $plan = SubscriptionPlan::factory()
        ->withTrial(30)
        ->create(['duration_days' => 30]);

    $subscription = Subscription::factory()
        ->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'yearly',
            'status' => SubscriptionStatus::PENDING,
            'starts_at' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

    $subscription->activate(withTrial: true);

    $expectedEndDate = now()->addDays(30 + 365);

    expect($subscription->fresh())
        ->status->toBe(SubscriptionStatus::ACTIVE)
        ->ends_at->not->toBeNull()
        ->and($subscription->ends_at->diffInDays($expectedEndDate, false))->toBeLessThan(1);
});

it('can convert from trial to paid', function (): void {
    $subscription = Subscription::factory()
        ->onTrial(14)
        ->create();

    expect($subscription->isOnTrial())->toBeTrue();

    $result = $subscription->convertFromTrial();

    expect($result)->toBeTrue();
    expect($subscription->fresh()->isOnTrial())->toBeFalse();
    expect($subscription->trialHasEnded())->toBeTrue();
});

it('returns false when converting non-trial subscription', function (): void {
    $subscription = Subscription::factory()
        ->create(['trial_ends_at' => null]);

    $result = $subscription->convertFromTrial();

    expect($result)->toBeFalse();
});

it('can renew subscription', function (): void {
    $subscription = Subscription::factory()
        ->create([
            'status' => SubscriptionStatus::EXPIRED,
            'renewal_count' => 2,
            'cancelled_at' => now()->subDays(5),
            'cancellation_reason' => 'Test reason',
        ]);

    $subscription->renew();

    expect($subscription->fresh())
        ->status->toBe(SubscriptionStatus::ACTIVE)
        ->renewal_count->toBe(3)
        ->renewed_at->not->toBeNull()
        ->ends_at->not->toBeNull()
        ->cancelled_at->toBeNull()
        ->cancellation_reason->toBeNull();
});

it('can upgrade to a different plan', function (): void {
    $basicPlan = SubscriptionPlan::factory()->basic()->create();
    $premiumPlan = SubscriptionPlan::factory()->premium()->create();

    $subscription = Subscription::factory()->create([
        'subscription_plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);

    $subscription->upgradeTo($premiumPlan, 'monthly');

    expect($subscription->fresh())
        ->previous_plan_id->toBe($basicPlan->id)
        ->subscription_plan_id->toBe($premiumPlan->id)
        ->billing_period->toBe('monthly')
        ->ends_at->not->toBeNull();
});

it('can downgrade to a different plan', function (): void {
    $premiumPlan = SubscriptionPlan::factory()->premium()->create();
    $basicPlan = SubscriptionPlan::factory()->basic()->create();

    $originalEndsAt = now()->addDays(20);

    $subscription = Subscription::factory()->create([
        'subscription_plan_id' => $premiumPlan->id,
        'billing_period' => 'yearly',
        'ends_at' => $originalEndsAt,
    ]);

    $subscription->downgradeTo($basicPlan, 'monthly');

    $refreshed = $subscription->fresh();

    expect($refreshed)
        ->previous_plan_id->toBe($premiumPlan->id)
        ->subscription_plan_id->toBe($basicPlan->id)
        ->billing_period->toBe('monthly')
        ->ends_at->not->toBeNull();

    expect($refreshed->ends_at->timestamp)->toBe($originalEndsAt->timestamp);
});
