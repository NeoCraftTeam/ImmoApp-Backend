<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\SponsorshipTier;
use App\Enums\SubscriptionStatus;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agency = Agency::factory()->create(['owner_id' => $this->owner->id]);
    $this->plan = SubscriptionPlan::factory()->create();
});

it('auto-sponsors a new ad when the agency has an active subscription', function (): void {
    Subscription::factory()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($this->owner);

    $ad = Ad::factory()->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->fresh();

    expect($ad->is_subscription_sponsored)->toBeTrue()
        ->and($ad->subscription_tier)->toBe(SponsorshipTier::SUBSCRIPTION);
});

it('does not auto-sponsor a new ad when the agency has no active subscription', function (): void {
    $this->actingAs($this->owner);

    $ad = Ad::factory()->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->fresh();

    expect($ad->is_subscription_sponsored)->toBeFalse()
        ->and($ad->subscription_tier)->toBeNull();
});

it('moves all agency ads to Subscription tier when a subscription activates', function (): void {
    $ads = Ad::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => false,
    ]);

    Subscription::factory()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    foreach ($ads as $ad) {
        $ad->refresh();
        expect($ad->is_subscription_sponsored)->toBeTrue()
            ->and($ad->subscription_tier)->toBe(SponsorshipTier::SUBSCRIPTION);
    }
});

it('promotes already-boosted ads to Premium tier when the subscription activates', function (): void {
    $boostedAd = Ad::factory()->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => false,
        'is_boosted' => true,
        'boost_score' => 100,
        'boost_expires_at' => now()->addDays(7),
    ]);

    $plainAd = Ad::factory()->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => false,
        'is_boosted' => false,
    ]);

    Subscription::factory()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    expect($boostedAd->fresh()->subscription_tier)->toBe(SponsorshipTier::PREMIUM)
        ->and($plainAd->fresh()->subscription_tier)->toBe(SponsorshipTier::SUBSCRIPTION);
});

it('reverts ads to Organic tier when the subscription expires', function (): void {
    $ads = Ad::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
    ]);

    $subscription = Subscription::factory()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $subscription->update(['status' => SubscriptionStatus::EXPIRED]);

    foreach ($ads as $ad) {
        $ad->refresh();
        expect($ad->is_subscription_sponsored)->toBeFalse()
            ->and($ad->subscription_tier)->toBe(SponsorshipTier::ORGANIC);
    }
});

it('keeps Manual tier when the subscription expires but the ad still has an active boost', function (): void {
    $boostedAd = Ad::factory()->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::PREMIUM->value,
        'is_boosted' => true,
        'boost_score' => 100,
        'boost_expires_at' => now()->addDays(7),
    ]);

    $plainAd = Ad::factory()->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
        'is_boosted' => false,
    ]);

    $subscription = Subscription::factory()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $subscription->update(['status' => SubscriptionStatus::EXPIRED]);

    expect($boostedAd->fresh()->subscription_tier)->toBe(SponsorshipTier::MANUAL)
        ->and($plainAd->fresh()->subscription_tier)->toBe(SponsorshipTier::ORGANIC);
});

it('removes auto-boost when the subscription is cancelled immediately', function (): void {
    $ads = Ad::factory()->count(2)->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
    ]);

    $subscription = Subscription::factory()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
    ]);

    $subscription->update(['status' => SubscriptionStatus::CANCELLED]);

    foreach ($ads as $ad) {
        $ad->refresh();
        expect($ad->is_subscription_sponsored)->toBeFalse()
            ->and($ad->subscription_tier)->toBe(SponsorshipTier::ORGANIC);
    }
});

it('promotes a subscription-sponsored ad to Premium when manually boosted', function (): void {
    Subscription::factory()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $ad = Ad::factory()->create([
        'agency_id' => $this->agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->fresh();

    expect($ad->subscription_tier)->toBe(SponsorshipTier::SUBSCRIPTION);

    $ad->boost(score: 100, durationDays: 7);

    expect($ad->fresh()->subscription_tier)->toBe(SponsorshipTier::PREMIUM);
});
