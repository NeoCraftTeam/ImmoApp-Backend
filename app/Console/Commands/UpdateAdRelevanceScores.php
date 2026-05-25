<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\AdInteraction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Re-indexes ads whose behavioral interaction counts have changed in the last N hours,
 * so that Meilisearch relevance_score reflects fresh CTR, contact, and unlock rates.
 *
 * This is the practical implementation of Sprint 4.3 (behavioral personalization).
 * Rather than a full ML pipeline, we re-compute the relevance_score formula
 * (CTR × 40 + rating × 30 + boost × 30 + contact_rate × 10) and push to Meilisearch.
 *
 * Usage:
 *   php artisan ads:update-relevance-scores         # last 24h (default)
 *   php artisan ads:update-relevance-scores --hours=48
 *   php artisan ads:update-relevance-scores --all   # full reindex (slow)
 *   php artisan ads:update-relevance-scores --dry-run
 */
final class UpdateAdRelevanceScores extends Command
{
    protected $signature = 'ads:update-relevance-scores
                            {--hours=24 : Only re-index ads with new interactions in the last N hours}
                            {--all : Reindex every active ad (overrides --hours, very slow)}
                            {--chunk=200 : Batch size}
                            {--dry-run : Print count without dispatching scout updates}';

    protected $description = 'Refresh relevance_score in Meilisearch based on recent AdInteraction activity';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');
        $hours = max(1, (int) $this->option('hours'));
        $chunk = max(10, min(500, (int) $this->option('chunk')));

        $this->info(sprintf(
            'Mode: %s | Window: %s | Chunk: %d',
            $dryRun ? 'dry-run' : 'live',
            $all ? 'ALL active ads' : "last {$hours}h",
            $chunk,
        ));

        // Find the ad IDs that had interaction activity in the window
        $adIdsQuery = $all
            ? null
            : AdInteraction::query()
                ->whereNotNull('ad_id')
                ->where('created_at', '>=', now()->subHours($hours))
                ->whereIn('type', [
                    AdInteraction::TYPE_VIEW,
                    AdInteraction::TYPE_UNLOCK,
                    AdInteraction::TYPE_FAVORITE,
                    AdInteraction::TYPE_CONTACT_CLICK,
                    AdInteraction::TYPE_PHONE_CLICK,
                ])
                ->distinct()
                ->pluck('ad_id');

        /** @var Builder<Ad> $query */
        $query = Ad::query()->visible()->whereIn('status', Ad::PUBLIC_STATUSES);

        if (!$all && $adIdsQuery !== null) {
            if ($adIdsQuery->isEmpty()) {
                $this->line('No interactions in the given window. Nothing to reindex.');

                return self::SUCCESS;
            }
            $query->whereIn('id', $adIdsQuery);
        }

        $total = $query->count();
        $dispatched = 0;

        $this->info("Ads to reindex: {$total}");

        if ($dryRun) {
            $this->warn('[dry-run] Skipping scout sync.');

            return self::SUCCESS;
        }

        // Eager-load aggregates needed by computeRelevanceScore()
        $query->withAvg('reviews as reviews_avg_rating', 'rating')
            ->withCount([
                'interactions as views_count' => fn (Builder $q) => $q->where('type', AdInteraction::TYPE_VIEW),
                'interactions as unlocked_count' => fn (Builder $q) => $q->where('type', AdInteraction::TYPE_UNLOCK),
                'interactions as contact_count' => fn (Builder $q) => $q->whereIn('type', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK]),
            ])
            ->with('adBoosts');

        $query->chunkById($chunk, function ($ads) use (&$dispatched): void {
            $ads->each(fn (Ad $ad) => $ad->searchable());
            $dispatched += $ads->count();
        });

        $this->info("Reindexed {$dispatched} ads.");

        Log::info('ads:update-relevance-scores', [
            'dispatched' => $dispatched,
            'total' => $total,
        ]);

        return self::SUCCESS;
    }
}
