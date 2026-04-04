<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendSearchAlertDigestJob;
use App\Models\SearchAlertMatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches one SendSearchAlertDigestJob per user that has pending
 * (unsent) search-alert matches.
 *
 * Scheduled twice daily (08:00 and 18:00) so users never wait more than
 * 10 hours for a notification about a matching ad.
 */
final class SendSearchAlertDigests extends Command
{
    protected $signature = 'app:send-search-alert-digests
                            {--dry-run : Print user IDs without dispatching jobs}';

    protected $description = 'Dispatch digest notifications for pending search-alert matches';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Find distinct users who have at least one pending match.
        $userIds = SearchAlertMatch::query()
            ->pending()
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->info('No pending matches — nothing to send.');

            return self::SUCCESS;
        }

        $this->info("Found {$userIds->count()} user(s) with pending matches.");

        $dispatched = 0;

        User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->chunkById(200, function ($users) use ($dryRun, &$dispatched): void {
                foreach ($users as $user) {
                    if ($dryRun) {
                        $this->line("  [dry-run] Would dispatch digest for user {$user->id} ({$user->email})");
                        $dispatched++;
                        continue;
                    }

                    SendSearchAlertDigestJob::dispatch($user)->onQueue('notifications');
                    $dispatched++;
                }
            });

        $verb = $dryRun ? 'Would dispatch' : 'Dispatched';
        $this->info("{$verb} {$dispatched} digest job(s).");

        Log::info('app:send-search-alert-digests', [
            'dispatched' => $dispatched,
            'dry_run'    => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
