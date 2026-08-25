<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\SponsorshipTier;
use App\Models\Ad;
use App\Services\Ad\AdFeedRankingService;
use Illuminate\Support\Facades\DB;

it('returns Premium tier when subscription stacks with active manual boost', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'is_boosted' => true,
        'boost_expires_at' => now()->addDays(5),
        'boost_score' => 100,
    ]);

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::PREMIUM)
        ->and($ad->rankingMultiplier())->toBe(2.5);
});

it('returns Subscription tier for subscription-only ads', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'is_boosted' => false,
        'boost_score' => 0,
    ]);

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::SUBSCRIPTION)
        ->and($ad->rankingMultiplier())->toBe(1.8);
});

it('returns Manual tier for ads with only an active manual boost', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'is_boosted' => true,
        'boost_expires_at' => now()->addDays(5),
        'boost_score' => 100,
    ]);

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::MANUAL)
        ->and($ad->rankingMultiplier())->toBe(1.5);
});

it('returns Organic tier for ads with no sponsorship signals', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'is_boosted' => false,
        'boost_score' => 0,
    ]);

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::ORGANIC)
        ->and($ad->rankingMultiplier())->toBe(1.0);
});

it('falls back to Subscription tier when the manual boost has expired', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'is_boosted' => true,
        'boost_expires_at' => now()->subDay(),
        'boost_score' => 50,
    ]);

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::SUBSCRIPTION)
        ->and($ad->rankingMultiplier())->toBe(1.8);
});

it('falls back to Organic tier when only an expired manual boost remains', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'is_boosted' => true,
        'boost_expires_at' => now()->subDay(),
        'boost_score' => 50,
    ]);

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::ORGANIC)
        ->and($ad->rankingMultiplier())->toBe(1.0);
});

it('computes ranking score with time decay', function (): void {
    $recentAd = Ad::factory()->create();
    DB::table('ad')->where('id', $recentAd->id)->update([
        'boost_score' => 100,
        'is_subscription_sponsored' => false,
        'is_boosted' => false,
        'created_at' => now()->subDay(),
    ]);

    $oldAd = Ad::factory()->create();
    DB::table('ad')->where('id', $oldAd->id)->update([
        'boost_score' => 100,
        'is_subscription_sponsored' => false,
        'is_boosted' => false,
        'created_at' => now()->subDays(60),
    ]);

    $ranker = app(AdFeedRankingService::class);

    expect($ranker->rankingScore($recentAd->fresh()))
        ->toBeGreaterThan($ranker->rankingScore($oldAd->fresh()));
});

it('applies rotation penalty for recently shown ads', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 100,
        'last_shown_at' => now()->subHours(2),
    ]);

    // Score without penalty would be 100 * 1.8 * timeDecay ≈ 180.
    // With the 0.7 rotation penalty applied it must stay under 180.
    expect(app(AdFeedRankingService::class)->rankingScore($ad))->toBeLessThan(180);
});

it('records impression and updates timestamp', function (): void {
    $ad = Ad::factory()->create([
        'impression_count' => 5,
        'last_shown_at' => null,
    ]);

    app(AdFeedRankingService::class)->recordImpression($ad);
    $ad->refresh();

    expect($ad->impression_count)->toBe(6)
        ->and($ad->last_shown_at)->not->toBeNull();
});

it('orders ads by sponsorship ranking', function (): void {
    $subscriptionAd = Ad::factory()->create([
        'is_subscription_sponsored' => true,
        'boost_score' => 50,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subDays(1),
    ]);

    $boostedAd = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 100,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now()->subDays(2),
    ]);

    $organicAd = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'boost_score' => 0,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
        'created_at' => now(),
    ]);

    $orderedAds = Ad::orderBySponsorship()->get();

    expect($orderedAds->first()->id)->toBe($subscriptionAd->id)
        ->and($orderedAds->skip(1)->first()->id)->toBe($boostedAd->id)
        ->and($orderedAds->last()->id)->toBe($organicAd->id);
});

it('invalidates the memoised sponsorship tier when a boost flag flips on the instance', function (): void {
    $ad = Ad::factory()->create([
        'is_subscription_sponsored' => false,
        'is_boosted' => false,
        'boost_score' => 0,
    ]);

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::ORGANIC);

    // Flip a tier input on the same instance — setAttribute() must drop the memo.
    $ad->is_subscription_sponsored = true;

    expect($ad->sponsorshipTier())->toBe(SponsorshipTier::SUBSCRIPTION);
});
