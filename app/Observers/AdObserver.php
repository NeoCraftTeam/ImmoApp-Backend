<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Events\AdCreated;
use App\Events\AdStatusTransitioned;
use App\Models\Ad;
use Illuminate\Support\Facades\Cache;

class AdObserver
{
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
        AdCreated::dispatch($ad);
        $this->invalidateFeedCache();
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
        if ($ad->wasChanged(['status', 'is_visible', 'boost_score'])) {
            $this->invalidateFeedCache();
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
    }

    /**
     * Handle the Ad "deleted" event.
     */
    public function deleted(Ad $ad): void
    {
        $this->invalidateFeedCache();
    }

    /**
     * Forget the per_page-keyed guest feed cache entries.
     *
     * The cache key pattern is `ads:feed:guest:first:pp={N}` for N in [1..50].
     * We forget the common page sizes; the rare custom sizes will refresh
     * naturally at TTL expiry (5 min).
     */
    private function invalidateFeedCache(): void
    {
        foreach ([15, 20, 30, 50] as $perPage) {
            Cache::forget("ads:feed:guest:first:pp={$perPage}");
        }

        // Guest recommendations cold-start may also include this ad.
        Cache::forget('reco_v2_guest');
    }
}
