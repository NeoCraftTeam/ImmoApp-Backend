<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SponsorshipTier;
use App\Enums\SubscriptionStatus;
use App\Models\Ad;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

final class SubscriptionObserver
{
    /**
     * Handle the Subscription "created" event.
     */
    public function created(Subscription $subscription): void
    {
        if ($subscription->status === SubscriptionStatus::ACTIVE) {
            $this->boostAllAgencyAds($subscription);
        }
    }

    /**
     * Handle the Subscription "updated" event.
     */
    public function updated(Subscription $subscription): void
    {
        if ($subscription->wasChanged('status')) {
            if ($subscription->status === SubscriptionStatus::ACTIVE) {
                $this->boostAllAgencyAds($subscription);
            } elseif (in_array($subscription->status, [SubscriptionStatus::EXPIRED, SubscriptionStatus::CANCELLED], true)) {
                $this->unboostAllAgencyAds($subscription);
            }
        }
    }

    /**
     * Flip every active ad of the agency to a sponsored tier.
     *
     * Ads that already carry an active manual boost reach Premium (2.5×);
     * the rest land in Subscription (1.8×).
     */
    private function boostAllAgencyAds(Subscription $subscription): void
    {
        $agencyId = $subscription->agency_id;

        if (!$agencyId) {
            return;
        }

        $baseQuery = Ad::query()
            ->where('agency_id', $agencyId)
            ->whereIn('status', ['available', 'reserved'])
            ->where('is_visible', true);

        $premiumCount = (clone $baseQuery)
            ->where('is_boosted', true)
            ->where('boost_expires_at', '>', now())
            ->update([
                'is_subscription_sponsored' => true,
                'subscription_tier' => SponsorshipTier::PREMIUM->value,
            ]);

        $subscriptionCount = (clone $baseQuery)
            ->where(function ($q): void {
                $q->where('is_boosted', false)
                    ->orWhereNull('boost_expires_at')
                    ->orWhere('boost_expires_at', '<=', now());
            })
            ->update([
                'is_subscription_sponsored' => true,
                'subscription_tier' => SponsorshipTier::SUBSCRIPTION->value,
            ]);

        if ($premiumCount > 0 || $subscriptionCount > 0) {
            // Mass UPDATE bypasses Eloquent observers — flush the guest feed
            // cache manually so the new sponsored placements appear immediately.
            AdObserver::invalidateFeedCache();
        }

        Log::info('Auto-boosted agency ads on subscription activation', [
            'subscription_id' => $subscription->id,
            'agency_id' => $agencyId,
            'premium_ads' => $premiumCount,
            'subscription_ads' => $subscriptionCount,
        ]);
    }

    /**
     * Strip the subscription flag from every agency ad.
     *
     * Ads keeping an active manual boost fall back to Manual tier (1.5×);
     * the rest become Organic (1.0×).
     */
    private function unboostAllAgencyAds(Subscription $subscription): void
    {
        $agencyId = $subscription->agency_id;

        if (!$agencyId) {
            return;
        }

        $baseQuery = Ad::query()
            ->where('agency_id', $agencyId)
            ->where('is_subscription_sponsored', true);

        $manualCount = (clone $baseQuery)
            ->where('is_boosted', true)
            ->where('boost_expires_at', '>', now())
            ->update([
                'is_subscription_sponsored' => false,
                'subscription_tier' => SponsorshipTier::MANUAL->value,
            ]);

        $organicCount = (clone $baseQuery)
            ->where(function ($q): void {
                $q->where('is_boosted', false)
                    ->orWhereNull('boost_expires_at')
                    ->orWhere('boost_expires_at', '<=', now());
            })
            ->update([
                'is_subscription_sponsored' => false,
                'subscription_tier' => SponsorshipTier::ORGANIC->value,
            ]);

        if ($manualCount > 0 || $organicCount > 0) {
            AdObserver::invalidateFeedCache();
        }

        Log::info('Removed auto-boost from agency ads on subscription expiration', [
            'subscription_id' => $subscription->id,
            'agency_id' => $agencyId,
            'kept_as_manual' => $manualCount,
            'demoted_to_organic' => $organicCount,
        ]);
    }
}
