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
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('flushes the guest feed cache when an ad flips to subscription-sponsored', function (): void {
    Cache::put('ads:feed:guest:first:pp=15', 'cached', 60);

    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ]);

    $ad->forceFill([
        'is_subscription_sponsored' => true,
        'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
    ])->save();

    expect(Cache::has('ads:feed:guest:first:pp=15'))->toBeFalse();
});

it('flushes the guest feed cache when a subscription activates and bulk-updates agency ads', function (): void {
    $owner = User::factory()->create();
    $agency = Agency::factory()->create(['owner_id' => $owner->id]);
    $plan = SubscriptionPlan::factory()->create();

    Ad::factory()->count(3)->state([
        'agency_id' => $agency->id,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'is_subscription_sponsored' => false,
    ])->create();

    Cache::put('ads:feed:guest:first:pp=15', 'cached', 60);
    Cache::put('ads:feed:guest:first:pp=20', 'cached', 60);

    Subscription::factory()->create([
        'agency_id' => $agency->id,
        'subscription_plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    expect(Cache::has('ads:feed:guest:first:pp=15'))->toBeFalse()
        ->and(Cache::has('ads:feed:guest:first:pp=20'))->toBeFalse();
});
