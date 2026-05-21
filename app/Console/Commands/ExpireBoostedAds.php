<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AdBoostService;
use App\Services\BoostService;
use Illuminate\Console\Command;

/**
 * Sweeps ads whose `boost_expires_at` is in the past and resets the boost
 * flags so the public listing stops promoting them.
 *
 * `Ad::isBoosted()` already double-checks `boost_expires_at > now()`, so a
 * stale row never appears at the top in *runtime* logic — but the row still
 * carries `is_boosted=true` and a positive `boost_score`, which leaks into
 * Meilisearch sorts (search ranks by `boost_score:desc`) and admin filters
 * until `unboost()` is called. Running this hourly keeps the index honest.
 */
final class ExpireBoostedAds extends Command
{
    protected $signature = 'app:expire-boosted-ads';

    protected $description = 'Reset is_boosted/boost_score for ads whose boost_expires_at is in the past.';

    public function handle(BoostService $boostService, AdBoostService $legacyService): int
    {
        // New path: expire credit-based ad_boosts and update their status column.
        $fromPacks = $boostService->expireStale();

        // Legacy path: any ad that is still marked boosted but has no ad_boost
        // record (subscription-based boosts or manual admin boosts).
        $legacy = $legacyService->removeExpiredBoosts();

        $this->info("Expired boosts: {$fromPacks} credit-based, {$legacy} legacy.");

        return self::SUCCESS;
    }
}
