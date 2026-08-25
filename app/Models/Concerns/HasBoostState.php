<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\SponsorshipTier;

/**
 * Boost lifecycle for an ad: applying/removing a paid or manual boost and
 * checking whether a boost is currently active. The persisted
 * `subscription_tier` column is kept in sync through {@see SponsorshipTier::fromFlags()}.
 */
trait HasBoostState
{
    /**
     * Boost this ad with a given score and duration
     */
    public function boost(int $score, int $durationDays): void
    {
        $this->forceFill([
            'is_boosted' => true,
            'boost_score' => $score,
            'boost_expires_at' => now()->addDays($durationDays),
            'boosted_at' => now(),
            'subscription_tier' => SponsorshipTier::fromFlags(
                (bool) $this->is_subscription_sponsored,
                true,
            ),
        ])->save();
    }

    /**
     * Remove boost from this ad
     */
    public function unboost(): void
    {
        $this->forceFill([
            'is_boosted' => false,
            'boost_score' => 0,
            'boost_expires_at' => null,
            'subscription_tier' => SponsorshipTier::fromFlags(
                (bool) $this->is_subscription_sponsored,
                false,
            ),
        ])->save();
    }

    /**
     * Check if ad is currently boosted
     */
    public function isBoosted(): bool
    {
        return $this->is_boosted
            && $this->boost_expires_at
            && $this->boost_expires_at->isFuture();
    }
}
