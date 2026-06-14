<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Enums\SponsorshipTier;
use App\Enums\SubscriptionStatus;
use App\Events\AdCreated;
use App\Events\AdStatusTransitioned;
use App\Jobs\PingIndexNowJob;
use App\Jobs\RecomputeAdDistancesJob;
use App\Models\Ad;
use Illuminate\Support\Facades\Cache;

class AdObserver
{
    public function __construct() {}

    /**
     * Ensure tour_config always has a default_scene set before saving.
     */
    public function saving(Ad $ad): void
    {
        if (!empty($ad->tour_config['scenes']) && empty($ad->tour_config['default_scene'])) {
            $config = $ad->tour_config;
            $config['default_scene'] = $config['scenes'][0]['id'];
            $ad->tour_config = $config;
        }
    }

    /**
     * Handle the Ad "created" event.
     */
    public function created(Ad $ad): void
    {
        // Auto-sponsor if agency has active subscription
        if ($ad->agency_id && !$ad->is_subscription_sponsored) {
            $hasActiveSubscription = $ad->agency
                ->subscriptions()
                ->where('status', SubscriptionStatus::ACTIVE)
                ->where('ends_at', '>', now())
                ->exists();

            if ($hasActiveSubscription) {
                $tier = SponsorshipTier::fromFlags(true, $ad->isBoosted());
                $ad->forceFill([
                    'is_subscription_sponsored' => true,
                    'subscription_tier' => $tier->value,
                ])->saveQuietly();
            }
        }

        AdCreated::dispatch($ad);
        self::invalidateFeedCache();
        if ($ad->status === AdStatus::AVAILABLE && $ad->slug) {
            PingIndexNowJob::dispatch($this->adUrl($ad->slug))->onQueue('default');
        }

        // Compute server-side proximity distances from the scorecard
        // service. Previously these were user-typed fields on the form
        // (a guess at best); now they're authoritative server values
        // matching the same nearest-POI logic that powers KeyScore.
        if ($ad->location !== null) {
            RecomputeAdDistancesJob::dispatch($ad->id)->onQueue('default');
        }
    }

    /**
     * Handle the Ad "updated" event.
     *
     * Dispatches AdStatusTransitioned when the status column changes.
     */
    public function updated(Ad $ad): void
    {
        // Invalidate feed cache when visibility-affecting columns change.
        // 5min TTL is fine for normal eventual consistency, but newly published
        // or unpublished ads should appear/disappear immediately.
        if ($ad->wasChanged(['status', 'is_visible', 'boost_score', 'is_subscription_sponsored', 'subscription_tier'])) {
            self::invalidateFeedCache();
        }

        // Recompute the persisted proximity distances when the ad's
        // geolocation actually moves. Skip when only a sibling column
        // changed (status / visibility / etc.) so we don't fire a
        // queued job on every routine save.
        if ($ad->wasChanged('location') && $ad->location !== null) {
            RecomputeAdDistancesJob::dispatch($ad->id)->onQueue('default');
        }

        if (!$ad->wasChanged('status')) {
            return;
        }

        $original = $ad->getOriginal('status');
        $oldStatus = $original instanceof AdStatus ? $original : AdStatus::tryFrom($original);
        $newStatus = $ad->status;

        if (!$oldStatus || $oldStatus === $newStatus) {
            return;
        }

        AdStatusTransitioned::dispatch($ad, $oldStatus, $newStatus);

        if ($newStatus === AdStatus::AVAILABLE && $ad->slug) {
            PingIndexNowJob::dispatch($this->adUrl($ad->slug))->onQueue('default');
        }
    }

    /**
     * Handle the Ad "deleted" event.
     */
    public function deleted(Ad $ad): void
    {
        self::invalidateFeedCache();
        if ($ad->slug) {
            PingIndexNowJob::dispatch($this->adUrl($ad->slug))->onQueue('default');
        }
    }

    private function adUrl(string $slug): string
    {
        $host = rtrim((string) config('services.indexnow.host', 'keyhome.app'), '/');

        return "https://{$host}/ads/{$slug}";
    }

    /**
     * Forget the per_page-keyed guest feed cache entries.
     *
     * The cache key pattern is `ads:feed:guest:first:pp={N}` for N in [1..50].
     * We forget the common page sizes; the rare custom sizes will refresh
     * naturally at TTL expiry (5 min).
     *
     * Public so callers that bypass model events (mass updates from
     * SubscriptionObserver, scheduled jobs) can flush the cache directly.
     */
    public static function invalidateFeedCache(): void
    {
        foreach ([15, 20, 30, 50] as $perPage) {
            Cache::forget("ads:feed:guest:first:pp={$perPage}");
        }

        // Guest recommendations cold-start may also include this ad.
        Cache::forget('reco_v2_guest');
    }
}
