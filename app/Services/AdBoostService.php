<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use App\Models\Agency;

class AdBoostService
{
    /**
     * Auto-boost an ad if the user's agency has an active subscription
     */
    public function autoBoostIfEligible(Ad $ad): void
    {
        $user = $ad->user;

        if (!$user) {
            return;
        }

        // Check if user belongs to an agency
        if (!$user->agency_id || !$user->agency) {
            return;
        }

        /** @var Agency $agency */
        $agency = $user->agency;

        // Check if agency has active subscription
        if (!$agency->hasActiveSubscription()) {
            return;
        }

        $subscription = $agency->getCurrentSubscription();

        if (!$subscription || !$subscription->plan) {
            return;
        }

        // Boost the ad
        $ad->boost(
            $subscription->plan->boost_score,
            $subscription->plan->boost_duration_days
        );
    }

    /**
     * Check and remove expired boosts
     */
    public function removeExpiredBoosts(): int
    {
        $expiredAds = Ad::where('is_boosted', true)
            ->where('boost_expires_at', '<=', now())
            ->get();

        foreach ($expiredAds as $ad) {
            $ad->unboost();
        }

        return $expiredAds->count();
    }

    /**
     * Boost all ads for an agency
     */
    public function boostAllAgencyAds(Agency $agency): int
    {
        $subscription = $agency->getCurrentSubscription();

        if (!$subscription) {
            return 0;
        }

        $count = 0;

        $agency->users()->get()->each(function ($user) use ($subscription, &$count): void {
            /** @var \App\Models\User $user */
            $user->ads()
                ->where('status', \App\Enums\AdStatus::AVAILABLE)
                ->get()
                ->each(function ($ad) use ($subscription, &$count): void {
                    /** @var \App\Models\Ad $ad */
                    $ad->boost(
                        $subscription->plan->boost_score,
                        $subscription->plan->boost_duration_days
                    );
                    $count++;
                });
        });

        return $count;
    }

    /**
     * Remove boost from all agency ads
     */
    public function unboostAllAgencyAds(Agency $agency): int
    {
        $count = 0;

        $agency->users()->get()->each(function ($user) use (&$count): void {
            /** @var \App\Models\User $user */
            $user->ads()
                ->where('is_boosted', true)
                ->get()
                ->each(function ($ad) use (&$count): void {
                    /** @var \App\Models\Ad $ad */
                    $ad->unboost();
                    $count++;
                });
        });

        return $count;
    }

    /**
     * Get boost statistics
     */
    public function getBoostStats(): array
    {
        return [
            'total_boosted' => Ad::boosted()->count(),
            'total_expired' => Ad::where('is_boosted', true)
                ->where('boost_expires_at', '<=', now())
                ->count(),
            'average_boost_score' => Ad::boosted()->avg('boost_score') ?? 0,
        ];
    }
}
