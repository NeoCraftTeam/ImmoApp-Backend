<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AdBoostService;
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

    public function handle(AdBoostService $service): int
    {
        $count = $service->removeExpiredBoosts();

        $this->info("Expired boosts removed: {$count}");

        return self::SUCCESS;
    }
}
