<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ad;
use App\Support\AdScoutSync;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * Deletes an ad and its media inside a transaction, then best-effort removes
 * it from the search index.
 *
 * Extracted from AdController::destroy() to mirror CreateAd / UpdateAd, so the
 * whole CRUD write surface lives in dedicated, reusable actions.
 */
final readonly class DeleteAd
{
    public function __construct(private LoggerInterface $log) {}

    /**
     * Delete the given ad — force-deleting when it is already soft-deleted —
     * and return the number of images it carried before deletion.
     */
    public function execute(Ad $ad): int
    {
        $imagesCount = DB::transaction(function () use ($ad): int {
            $this->log->info('Starting deletion of ad with ID: '.$ad->id);

            $count = $ad->getMedia('images')->count();

            Ad::withoutSyncingToSearch(function () use ($ad): void {
                if ($ad->trashed()) {
                    $ad->forceDelete();
                } else {
                    $ad->delete();
                }
            });

            $this->log->info('Ad deleted successfully with ID: '.$ad->id);

            return $count;
        });

        // Best-effort: remove from search index after the DB write has succeeded.
        // Meilisearch being down must not prevent a successful deletion.
        AdScoutSync::removeFromSearchIndexBestEffort($ad);

        return $imagesCount;
    }
}
