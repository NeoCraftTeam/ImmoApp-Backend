<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Enums\SponsorshipTier;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\SponsoredImpression;
use App\Models\User;
use App\Services\Ad\AdFeedRankingService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->service = app(AdFeedRankingService::class);
});

/**
 * Helper: factory shortcut for tier-stamped ads.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeTieredAd(SponsorshipTier $tier, array $overrides = []): Ad
{
    $base = match ($tier) {
        SponsorshipTier::PREMIUM => [
            'is_subscription_sponsored' => true,
            'is_boosted' => true,
            'boost_expires_at' => now()->addDays(7),
            'boost_score' => 100,
        ],
        SponsorshipTier::SUBSCRIPTION => [
            'is_subscription_sponsored' => true,
            'is_boosted' => false,
            'boost_score' => 0,
        ],
        SponsorshipTier::MANUAL => [
            'is_subscription_sponsored' => false,
            'is_boosted' => true,
            'boost_expires_at' => now()->addDays(7),
            'boost_score' => 50,
        ],
        SponsorshipTier::ORGANIC => [
            'is_subscription_sponsored' => false,
            'is_boosted' => false,
            'boost_score' => 0,
        ],
    };

    return Ad::factory()->create(array_merge(
        ['status' => AdStatus::AVAILABLE, 'is_visible' => true],
        $base,
        $overrides,
    ));
}

it('fills the 10-slot template when every tier is well-stocked', function (): void {
    $candidates = collect([
        ...Ad::factory()->count(5)->state(['is_subscription_sponsored' => true, 'is_boosted' => true, 'boost_expires_at' => now()->addDays(7), 'boost_score' => 100])->create(),
        ...Ad::factory()->count(5)->state(['is_subscription_sponsored' => true, 'is_boosted' => false])->create(),
        ...Ad::factory()->count(5)->state(['is_subscription_sponsored' => false, 'is_boosted' => true, 'boost_expires_at' => now()->addDays(7), 'boost_score' => 50])->create(),
        ...Ad::factory()->count(5)->state(['is_subscription_sponsored' => false, 'is_boosted' => false])->create(),
    ]);

    $page = $this->service->distribute($candidates, 10);

    $tiers = $page->map(fn (Ad $ad) => $ad->sponsorshipTier())->values()->all();

    expect($tiers)->toBe([
        SponsorshipTier::PREMIUM,
        SponsorshipTier::PREMIUM,
        SponsorshipTier::PREMIUM,
        SponsorshipTier::SUBSCRIPTION,
        SponsorshipTier::ORGANIC,
        SponsorshipTier::SUBSCRIPTION,
        SponsorshipTier::SUBSCRIPTION,
        SponsorshipTier::MANUAL,
        SponsorshipTier::ORGANIC,
        SponsorshipTier::ORGANIC,
    ]);
});

it('falls back to lower tiers when the primary bucket is empty', function (): void {
    // No Premium ads exist — slots 0-2 must fall back through Subscription/Manual/Organic.
    $candidates = collect([
        ...Ad::factory()->count(4)->state(['is_subscription_sponsored' => true, 'is_boosted' => false])->create(),
        ...Ad::factory()->count(3)->state(['is_subscription_sponsored' => false, 'is_boosted' => true, 'boost_expires_at' => now()->addDays(7), 'boost_score' => 50])->create(),
        ...Ad::factory()->count(3)->state(['is_subscription_sponsored' => false, 'is_boosted' => false])->create(),
    ]);

    $page = $this->service->distribute($candidates, 10);

    expect($page)->toHaveCount(10)
        ->and($page->contains(fn (Ad $ad) => $ad->sponsorshipTier() === SponsorshipTier::PREMIUM))->toBeFalse()
        // First three slots accept Subscription (next-best after Premium).
        ->and($page->take(3)->every(fn (Ad $ad) => $ad->sponsorshipTier() === SponsorshipTier::SUBSCRIPTION))->toBeTrue();
});

it('caps each advertiser at 3 ads per page', function (): void {
    $heavyAgency = Agency::factory()->create();

    // 6 sponsored ads from the same agency — only 3 should appear on the page.
    Ad::factory()->count(6)->state([
        'agency_id' => $heavyAgency->id,
        'is_subscription_sponsored' => true,
        'is_boosted' => false,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->create();

    // Plus 10 unrelated ads so the page can be filled past the cap.
    Ad::factory()->count(10)->state([
        'is_subscription_sponsored' => false,
        'is_boosted' => false,
        'status' => AdStatus::AVAILABLE,
        'is_visible' => true,
    ])->create();

    $candidates = Ad::query()->visible()->get();
    $page = $this->service->distribute($candidates, 10);

    $perAdvertiser = $page->groupBy('agency_id')->map->count();

    expect($perAdvertiser[$heavyAgency->id])->toBe(3);
});

it('keeps low-rated ads out of the top three positions', function (): void {
    $lowRated = makeTieredAd(SponsorshipTier::PREMIUM);
    DB::table('ad')->where('id', $lowRated->id)->update([
        'created_at' => now()->subDay(),
    ]);
    $lowRated->reviews_avg_rating = 1.5;
    $lowRated->reviews_count = 10;

    $goodAds = Ad::factory()->count(2)->state([
        'is_subscription_sponsored' => true,
        'is_boosted' => true,
        'boost_expires_at' => now()->addDays(7),
        'boost_score' => 100,
    ])->create();
    foreach ($goodAds as $g) {
        $g->reviews_avg_rating = 4.5;
        $g->reviews_count = 5;
    }

    // Plus enough filler so slots 3-9 are reachable.
    Ad::factory()->count(20)->state(['is_subscription_sponsored' => false])->create();

    $candidates = collect([$lowRated, ...$goodAds, ...Ad::query()->whereNotIn('id', [$lowRated->id, ...$goodAds->pluck('id')->all()])->get()]);

    $page = $this->service->distribute($candidates, 10);

    $topThreeIds = $page->take(3)->pluck('id')->all();

    expect($topThreeIds)->not->toContain($lowRated->id);
});

it('batches impression writes into a single counter update + a single inserts statement', function (): void {
    $ads = Ad::factory()->count(3)->state([
        'impression_count' => 4,
        'last_shown_at' => null,
    ])->create();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->service->recordImpressions(collect($ads));

    $log = DB::getQueryLog();
    $updates = array_filter($log, fn ($q) => str_starts_with(strtolower((string) $q['query']), 'update'));
    $inserts = array_filter($log, fn ($q) => str_starts_with(strtolower((string) $q['query']), 'insert'));

    expect($updates)->toHaveCount(1)
        ->and($inserts)->toHaveCount(1);

    foreach ($ads as $ad) {
        $ad->refresh();
        expect($ad->impression_count)->toBe(5)
            ->and($ad->last_shown_at)->not->toBeNull();
    }

    expect(SponsoredImpression::query()->count())->toBe(3);
});

it('logs each sponsored impression with the right tier, slot, and viewer', function (): void {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    $candidates = collect([
        ...Ad::factory()->count(3)->state(['is_subscription_sponsored' => true, 'is_boosted' => true, 'boost_expires_at' => now()->addDays(7), 'boost_score' => 100])->create(),
        ...Ad::factory()->count(3)->state(['is_subscription_sponsored' => true, 'is_boosted' => false])->create(),
        ...Ad::factory()->count(2)->state(['is_subscription_sponsored' => false, 'is_boosted' => true, 'boost_expires_at' => now()->addDays(7), 'boost_score' => 50])->create(),
        ...Ad::factory()->count(2)->state(['is_subscription_sponsored' => false, 'is_boosted' => false])->create(),
    ]);

    $page = $this->service->distribute($candidates, 10);
    $this->service->recordImpressions($page);

    $impressions = SponsoredImpression::query()->orderBy('slot')->get();

    expect($impressions)->toHaveCount(10);

    foreach ($impressions as $imp) {
        expect($imp->user_id)->toBe($viewer->id)
            ->and($imp->slot)->toBeGreaterThanOrEqual(0)
            ->and($imp->slot)->toBeLessThan(10);
    }

    // Slot 0 must hold a Premium impression (template position 0 = Premium, and we seeded premium ads).
    expect($impressions->firstWhere('slot', 0)->tier)->toBe(SponsorshipTier::PREMIUM);
    // Slot 7 must hold a Manual impression.
    expect($impressions->firstWhere('slot', 7)->tier)->toBe(SponsorshipTier::MANUAL);
});

it('records guest impressions with a null user_id', function (): void {
    $ads = Ad::factory()->count(2)->state([
        'is_subscription_sponsored' => true,
        'is_boosted' => false,
    ])->create();

    $page = $this->service->distribute(collect($ads), 2);
    $this->service->recordImpressions($page);

    expect(SponsoredImpression::query()->pluck('user_id')->unique()->all())->toBe([null]);
});
