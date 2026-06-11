<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ranking tier an ad currently holds in the sponsored-feed system.
 *
 * Derived from two independent flags on the ad:
 *   - is_subscription_sponsored (the owning agency has an active subscription)
 *   - is_boosted               (a manual boost is active)
 *
 * The combination yields four discrete ranking tiers with distinct multipliers
 * applied on top of the base relevance/boost score.
 */
enum SponsorshipTier: string
{
    case PREMIUM = 'premium';
    case SUBSCRIPTION = 'subscription';
    case MANUAL = 'manual';
    case ORGANIC = 'organic';

    /**
     * Score multiplier applied during feed ranking.
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::PREMIUM => 2.5,
            self::SUBSCRIPTION => 1.8,
            self::MANUAL => 1.5,
            self::ORGANIC => 1.0,
        };
    }

    /**
     * Resolve the tier from the two underlying flags.
     */
    public static function fromFlags(bool $subscriptionSponsored, bool $manuallyBoosted): self
    {
        return match (true) {
            $subscriptionSponsored && $manuallyBoosted => self::PREMIUM,
            $subscriptionSponsored => self::SUBSCRIPTION,
            $manuallyBoosted => self::MANUAL,
            default => self::ORGANIC,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PREMIUM => 'Premium Sponsorisé',
            self::SUBSCRIPTION => 'Sponsorisé',
            self::MANUAL => 'Boosté',
            self::ORGANIC => 'Organique',
        };
    }

    /**
     * Hex color hint for badges (frontend may map to its design tokens).
     */
    public function color(): string
    {
        return match ($this) {
            self::PREMIUM => 'gold',
            self::SUBSCRIPTION => 'purple',
            self::MANUAL => 'blue',
            self::ORGANIC => 'gray',
        };
    }

    /**
     * Whether the tier should render a "sponsored" disclosure badge.
     */
    public function isSponsored(): bool
    {
        return $this !== self::ORGANIC;
    }
}
