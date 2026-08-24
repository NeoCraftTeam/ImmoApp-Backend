<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\SponsorshipTier;

/**
 * Sponsorship-tier derivation for an ad: the memoised current tier, its
 * ranking multiplier, and synchronisation of the denormalised
 * `subscription_tier` column. The tier math itself lives on the
 * {@see SponsorshipTier} enum; this trait only carries the instance-level
 * memo and its invalidation.
 */
trait HasSponsorshipTier
{
    /**
     * Lazy cache for sponsorshipTier(). The flags it depends on
     * (is_subscription_sponsored, is_boosted, boost_expires_at) flip
     * via mutators that all funnel through setAttribute(), which
     * resets this field — so the cache invariant holds: a null cache
     * means "needs recompute".
     */
    private ?SponsorshipTier $cachedSponsorshipTier = null;

    /**
     * Derive the current sponsorship tier from the underlying flags.
     *
     * Canonical truth — the persisted `subscription_tier` column is a
     * denormalised copy kept in sync via boost()/unboost() and observers.
     *
     * Memoised because the feed render path calls this three times per
     * ad (twice in AdResource, once in AdFeedRankingService::bucketize).
     * The cache lives on the instance and is dropped whenever any input
     * flag is reassigned via setAttribute().
     */
    public function sponsorshipTier(): SponsorshipTier
    {
        return $this->cachedSponsorshipTier ??= SponsorshipTier::fromFlags(
            (bool) $this->is_subscription_sponsored,
            $this->isBoosted(),
        );
    }

    /**
     * Reset the sponsorship-tier memo on any write to its inputs.
     *
     * Eloquent funnels every property write through `setAttribute`
     * (including `forceFill`, mass assignment, and the `$ad->foo = bar`
     * style). Intercepting it here is the cheapest place to keep the
     * memo invariant intact without scattering invalidation calls
     * across every mutator.
     */
    #[\Override]
    public function setAttribute($key, $value)
    {
        if (in_array($key, ['is_subscription_sponsored', 'is_boosted', 'boost_expires_at', 'subscription_tier'], true)) {
            $this->cachedSponsorshipTier = null;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Ranking multiplier for the sponsored-feed algorithm.
     */
    public function rankingMultiplier(): float
    {
        return $this->sponsorshipTier()->multiplier();
    }

    /**
     * Recompute and persist `subscription_tier` from current flags.
     */
    public function syncSponsorshipTier(): void
    {
        $tier = $this->sponsorshipTier();

        if ($this->subscription_tier === $tier) {
            return;
        }

        $this->forceFill(['subscription_tier' => $tier])->saveQuietly();
    }
}
