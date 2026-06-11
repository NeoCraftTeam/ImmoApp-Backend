<?php

declare(strict_types=1);

use App\Enums\SponsorshipTier;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\SponsoredImpression;
use App\Models\User;
use App\Services\Ad\SponsorshipAnalyticsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->service = app(SponsorshipAnalyticsService::class);
    $this->from = now()->subDays(7);
    // Push the upper bound forward so impressions seeded inside the test
    // (which call now() after beforeEach has already captured the timestamp)
    // still fall inside the window.
    $this->to = now()->addHour();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function seedImpression(Ad $ad, SponsorshipTier $tier, int $slot, array $overrides = []): SponsoredImpression
{
    return SponsoredImpression::query()->create(array_merge([
        'ad_id' => $ad->id,
        'user_id' => null,
        'tier' => $tier->value,
        'slot' => $slot,
        'shown_at' => now(),
    ], $overrides));
}

function seedInteraction(Ad $ad, User $user, string $type, ?Carbon $at = null): void
{
    AdInteraction::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'ad_id' => $ad->id,
        'type' => $type,
        'created_at' => $at ?? now(),
    ]);
}

it('returns zeros for every tier when there is no telemetry', function (): void {
    $metrics = $this->service->tierMetrics($this->from, $this->to);

    foreach (SponsorshipTier::cases() as $tier) {
        expect($metrics[$tier->value])->toBe([
            'impressions' => 0,
            'views' => 0,
            'unlocks' => 0,
            'view_rate' => 0.0,
            'unlock_rate' => 0.0,
        ]);
    }
});

it('counts impressions per tier from sponsored_impressions', function (): void {
    $premiumAd = Ad::factory()->create(['subscription_tier' => SponsorshipTier::PREMIUM->value]);
    $subAd = Ad::factory()->create(['subscription_tier' => SponsorshipTier::SUBSCRIPTION->value]);

    seedImpression($premiumAd, SponsorshipTier::PREMIUM, 0);
    seedImpression($premiumAd, SponsorshipTier::PREMIUM, 1);
    seedImpression($subAd, SponsorshipTier::SUBSCRIPTION, 3);

    $metrics = $this->service->tierMetrics($this->from, $this->to);

    expect($metrics['premium']['impressions'])->toBe(2)
        ->and($metrics['subscription']['impressions'])->toBe(1)
        ->and($metrics['manual']['impressions'])->toBe(0)
        ->and($metrics['organic']['impressions'])->toBe(0);
});

it('attributes views and unlocks to each ad\'s current tier and computes rates', function (): void {
    $user = User::factory()->create();
    $premiumAd = Ad::factory()->create(['subscription_tier' => SponsorshipTier::PREMIUM->value]);

    // 4 impressions, 2 views, 1 unlock on the same Premium ad → 50% / 25%.
    foreach (range(0, 3) as $i) {
        seedImpression($premiumAd, SponsorshipTier::PREMIUM, $i);
    }
    seedInteraction($premiumAd, $user, AdInteraction::TYPE_VIEW);
    seedInteraction($premiumAd, $user, AdInteraction::TYPE_VIEW);
    seedInteraction($premiumAd, $user, AdInteraction::TYPE_UNLOCK);

    $metrics = $this->service->tierMetrics($this->from, $this->to);

    expect($metrics['premium']['impressions'])->toBe(4)
        ->and($metrics['premium']['views'])->toBe(2)
        ->and($metrics['premium']['unlocks'])->toBe(1)
        ->and($metrics['premium']['view_rate'])->toBe(50.0)
        ->and($metrics['premium']['unlock_rate'])->toBe(25.0);
});

it('ignores telemetry outside the requested window', function (): void {
    $ad = Ad::factory()->create(['subscription_tier' => SponsorshipTier::PREMIUM->value]);
    $user = User::factory()->create();

    seedImpression($ad, SponsorshipTier::PREMIUM, 0, ['shown_at' => now()->subDays(30)]);
    seedInteraction($ad, $user, AdInteraction::TYPE_VIEW, now()->subDays(30));

    $metrics = $this->service->tierMetrics($this->from, $this->to);

    expect($metrics['premium']['impressions'])->toBe(0)
        ->and($metrics['premium']['views'])->toBe(0);
});
