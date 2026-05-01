<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\City;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Refresh the landing-page stats and testimonials caches before TTL expiry.
 *
 * The COUNT(*) on the Ads table is the dominant cost on cold-cache reads
 * (the endpoint exceeded the 1 s Nightwatch threshold). Running this every
 * 25 minutes keeps the 30-min cache always warm without locking traffic.
 */
final class WarmLandingStatsCache extends Command
{
    protected $signature = 'app:warm-landing-stats';

    protected $description = 'Recompute and cache landing-page stats + testimonials so end-users never hit a cold cache.';

    public function handle(): int
    {
        $stats = [
            'ads_count' => Ad::query()->publiclyListed()->where('is_visible', true)->count(),
            'cities_count' => City::query()->count(),
            'users_count' => User::query()->count(),
        ];
        Cache::put('landing:stats', $stats, now()->addMinutes(30));
        $this->info('Warmed landing:stats: '.json_encode($stats));

        return self::SUCCESS;
    }
}
