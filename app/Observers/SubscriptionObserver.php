<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SponsorshipTier;
use App\Enums\SubscriptionStatus;
use App\Models\Ad;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
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

        // Single UPDATE branching on `is_boosted + boost_expires_at` in SQL.
        // Was two cloned queries that scanned the agency's ads twice;
        // collapsing to a CASE expression halves the work and matches one
        // logical observer event to one DB write.
        $premium = SponsorshipTier::PREMIUM->value;
        $sponsored = SponsorshipTier::SUBSCRIPTION->value;
        $count = Ad::query()
            ->where('agency_id', $agencyId)
            ->whereIn('status', ['available', 'reserved'])
            ->where('is_visible', true)
            ->update([
                'is_subscription_sponsored' => true,
                'subscription_tier' => DB::raw(
                    'CASE WHEN is_boosted = true AND boost_expires_at > NOW() '
                    ."THEN '{$premium}' ELSE '{$sponsored}' END"
                ),
            ]);

        if ($count > 0) {
            // Mass UPDATE bypasses Eloquent observers — flush the guest feed
            // cache manually so the new sponsored placements appear immediately.
            AdObserver::invalidateFeedCache();
        }

        Log::info('Auto-boosted agency ads on subscription activation', [
            'subscription_id' => $subscription->id,
            'agency_id' => $agencyId,
            'ads_updated' => $count,
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

        // Single UPDATE branching in SQL. Symmetric with boostAllAgencyAds:
        // one logical event → one DB write, halving the agency scan.
        $manual = SponsorshipTier::MANUAL->value;
        $organic = SponsorshipTier::ORGANIC->value;
        $count = Ad::query()
            ->where('agency_id', $agencyId)
            ->where('is_subscription_sponsored', true)
            ->update([
                'is_subscription_sponsored' => false,
                'subscription_tier' => DB::raw(
                    'CASE WHEN is_boosted = true AND boost_expires_at > NOW() '
                    ."THEN '{$manual}' ELSE '{$organic}' END"
                ),
            ]);

        if ($count > 0) {
            AdObserver::invalidateFeedCache();
        }

        Log::info('Removed auto-boost from agency ads on subscription expiration', [
            'subscription_id' => $subscription->id,
            'agency_id' => $agencyId,
            'ads_updated' => $count,
        ]);
    }
}
