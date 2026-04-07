<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ad;
use App\Enums\AdStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * GDPR / P2-29: Hard-delete user accounts that were soft-deleted more than 2 years ago.
 *
 * Cascades:
 *   - Sanctum tokens (via $user->tokens()->delete())
 *   - Spatie MediaLibrary files (via $user->media()->delete())
 *   - The User record itself (forceDelete)
 *
 * OTP cache entries in Redis expire automatically via TTL — no manual cleanup needed here.
 *
 * Schedule: daily at 03:30 (off-peak window, after auto-hide-stale-ads at 03:00).
 */
final class PurgeExpiredData extends Command
{
    protected $signature = 'app:purge-expired-data
        {--dry-run : Log what would be deleted without actually deleting}
        {--years=2 : Soft-delete age threshold in years (default: 2)}';

    protected $description = 'Hard-delete soft-deleted users older than the retention threshold (GDPR compliance).';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subYears($years);
        $purged = 0;

        $this->info(sprintf(
            '%s users soft-deleted before %s …',
            $dryRun ? '[DRY RUN] Would purge' : 'Purging',
            $cutoff->toDateString()
        ));

        User::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->chunkById(100, function ($users) use ($dryRun, &$purged): void {
                foreach ($users as $user) {
                    if ($dryRun) {
                        $this->line("  [dry-run] Would delete user {$user->id} ({$user->email}), deleted_at={$user->deleted_at}");
                        $purged++;

                        continue;
                    }

                    // 1. Revoke all Sanctum tokens
                    $user->tokens()->delete();

                    // 2. Delete Spatie media files (cascades to disk)
                    try {
                        /** @phpstan-ignore method.notFound */
                        $user->clearMediaCollections();
                    } catch (\Throwable) {
                        // Non-fatal — continue purge even if media cleanup fails
                    }

                    // 3. Hard-delete the record
                    $user->forceDelete();
                    $purged++;
                }
            });

        $verb = $dryRun ? 'Would have purged' : 'Purged';
        $this->info("{$verb} {$purged} user(s).");

        Log::channel('audit')->info('GDPR purge completed', [
            'dry_run' => $dryRun,
            'users_purged' => $purged,
            'retention_years' => $years,
            'cutoff_date' => $cutoff->toDateString(),
        ]);

        // ── Stale draft ads (30-day TTL) ──────────────────────────────────────
        $draftCutoff = now()->subDays(30);
        $draftsPurged = 0;

        $this->info(sprintf(
            '%s draft ads not updated since %s …',
            $dryRun ? '[DRY RUN] Would soft-delete' : 'Soft-deleting',
            $draftCutoff->toDateString()
        ));

        Ad::withoutGlobalScopes()
            ->where('status', AdStatus::DRAFT->value)
            ->where('updated_at', '<', $draftCutoff)
            ->chunkById(100, function ($ads) use ($dryRun, &$draftsPurged): void {
                foreach ($ads as $ad) {
                    if ($dryRun) {
                        $this->line("  [dry-run] Would soft-delete draft ad {$ad->id}, updated_at={$ad->updated_at}");
                        $draftsPurged++;

                        continue;
                    }

                    $ad->delete();
                    $draftsPurged++;
                }
            });

        $draftVerb = $dryRun ? 'Would have soft-deleted' : 'Soft-deleted';
        $this->info("{$draftVerb} {$draftsPurged} stale draft ad(s).");

        Log::channel('audit')->info('Stale draft ads purge completed', [
            'dry_run' => $dryRun,
            'drafts_purged' => $draftsPurged,
            'cutoff_date' => $draftCutoff->toDateString(),
        ]);

        return self::SUCCESS;
    }
}
