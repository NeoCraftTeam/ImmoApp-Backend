<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Ad;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Defers Scout/Meilisearch syncing so a dead search engine cannot roll back
 * successful database writes. After the ad is persisted, sync is attempted once;
 * failures are logged only.
 */
final class AdScoutSync
{
    public static function syncSearchIndexBestEffort(Ad $ad): void
    {
        try {
            if ($ad->shouldBeSearchable()) {
                $ad->searchable();
            } else {
                $ad->unsearchable();
            }
        } catch (Throwable $e) {
            Log::warning('Scout search index sync failed; database row is authoritative.', [
                'ad_id' => $ad->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the ad from the search index after a soft or force delete.
     * Failures are logged and swallowed so a dead Meilisearch cannot block deletion.
     */
    public static function removeFromSearchIndexBestEffort(Ad $ad): void
    {
        try {
            $ad->unsearchable();
        } catch (Throwable $e) {
            Log::warning('Scout index removal after delete failed; deletion itself succeeded.', [
                'ad_id' => $ad->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
