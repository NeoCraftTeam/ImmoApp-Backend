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

it('orders ads by sponsorship tier in direct queries', function (): void {
    Ad::query()->forceDelete();

    $owner = User::factory()->create();
    $agency = Agency::factory()->create(['owner_id' => $owner->id]);
    $plan = SubscriptionPlan::factory()->create();

    Subscription::factory()->create([
        'agency_id' => $agency->id,
        'subscription_plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $sponsoredAd = Ad::factory()->create([
        'agency_id' => $agency->id,
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'boost_score' => 50,
    ]);

    $boostedAd = Ad::factory()->create([
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'boost_score' => 200,
        'is_subscription_sponsored' => false,
        'is_boosted' => true,
        'boost_expires_at' => now()->addDays(7),
        'subscription_tier' => SponsorshipTier::MANUAL->value,
    ]);

    $organicAd = Ad::factory()->create([
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'boost_score' => 0,
        'is_subscription_sponsored' => false,
        'is_boosted' => false,
    ]);

    $ids = Ad::visible()->publiclyListed()->orderBySponsorship()->get()->pluck('id')->all();

    expect($ids[0])->toBe($sponsoredAd->id)
        ->and($ids[1])->toBe($boostedAd->id)
        ->and($ids[2])->toBe($organicAd->id);
});

it('exposes the derived sponsorship tier on the ad model', function (): void {
    $owner = User::factory()->create();
    $agency = Agency::factory()->create(['owner_id' => $owner->id]);
    $plan = SubscriptionPlan::factory()->create();

    Subscription::factory()->create([
        'agency_id' => $agency->id,
        'subscription_plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $sponsoredAd = Ad::factory()->create([
        'agency_id' => $agency->id,
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->fresh();

    expect($sponsoredAd->is_subscription_sponsored)->toBeTrue()
        ->and($sponsoredAd->sponsorshipTier())->toBe(SponsorshipTier::SUBSCRIPTION)
        ->and($sponsoredAd->rankingMultiplier())->toBe(1.8);
});

it('orders ads with same sponsorship by boost score', function (): void {
    $highBoostAd = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 200,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ]);

    $lowBoostAd = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 50,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ]);

    $response = $this->getJson('/api/v1/ads');

    $response->assertSuccessful();

    $adIds = collect($response->json('data'))->pluck('id')->all();

    expect(array_search($highBoostAd->id, $adIds))
        ->toBeLessThan(array_search($lowBoostAd->id, $adIds));
});
