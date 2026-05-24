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
 * --frequency=immediate  (default) : alerts set to "immediate" — runs every digest cycle
 * --frequency=daily                 : alerts set to "daily"     — run once per day (08:00)
 * --frequency=weekly                : alerts set to "weekly"    — run once per week (Monday 08:00)
 */
final class SendSearchAlertDigests extends Command
{
    protected $signature = 'app:send-search-alert-digests
                            {--dry-run : Print user IDs without dispatching jobs}
                            {--frequency=immediate : Filter alerts by frequency (immediate|daily|weekly)}';

    protected $description = 'Dispatch digest notifications for pending search-alert matches';

    /** @var string[] */
    private const array VALID_FREQUENCIES = ['immediate', 'daily', 'weekly'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $frequency = (string) $this->option('frequency');

        if (!in_array($frequency, self::VALID_FREQUENCIES, true)) {
            $this->error("Invalid frequency: {$frequency}. Must be one of: ".implode(', ', self::VALID_FREQUENCIES));

            return self::FAILURE;
        }

        // Find distinct users who have at least one pending match for the given frequency.
        $userIds = SearchAlertMatch::query()
            ->pending()
            ->whereHas('searchAlert', fn ($q) => $q->where('frequency', $frequency))
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
            ->chunkById(200, function ($users) use ($dryRun, $frequency, &$dispatched): void {
                foreach ($users as $user) {
                    if ($dryRun) {
                        $this->line("  [dry-run] Would dispatch digest for user {$user->id} ({$user->email})");
                        $dispatched++;

                        continue;
                    }

                    SendSearchAlertDigestJob::dispatch($user, $frequency)->onQueue('notifications');
                    $dispatched++;
                }
            });

        $verb = $dryRun ? 'Would dispatch' : 'Dispatched';
        $this->info("{$verb} {$dispatched} digest job(s).");

        Log::info('app:send-search-alert-digests', [
            'frequency' => $frequency,
            'dispatched' => $dispatched,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
