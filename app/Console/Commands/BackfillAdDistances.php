<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RecomputeAdDistancesJob;
use App\Models\Ad;
use Illuminate\Console\Command;

/**
 * Backfill `distance_*_m` columns for ads created before
 * RecomputeAdDistancesJob was wired into the AdObserver.
 *
 * The job itself is idempotent — re-running on a previously
 * computed ad just overwrites with the latest scorecard read —
 * so this command is safe to invoke multiple times.
 *
 * Defaults to `--only-missing` semantics by considering an ad
 * "missing" if any of the four scorecard-backed columns
 * (transport, shops, school, hospital) is null. Pass `--all` to
 * force a recompute on every ad with a location regardless of
 * current state.
 */
final class BackfillAdDistances extends Command
{
    protected $signature = 'ads:backfill-distances
                            {--ad= : Process a specific ad ID only}
                            {--all : Recompute every ad with a location, not just those missing a value}
                            {--limit= : Max number of ads to enqueue}
                            {--queue=default : Queue to dispatch the job onto}
                            {--sync : Run the job inline instead of dispatching to the queue}';

    protected $description = 'Dispatch RecomputeAdDistancesJob for ads with a location but missing computed distance_*_m columns.';

    public function handle(): int
    {
        $query = Ad::query()->whereNotNull('location');

        if ($adId = $this->option('ad')) {
            $query->where('id', $adId);
        }

        if (!$this->option('all')) {
            $query->where(function ($q): void {
                $q->whereNull('distance_transport_m')
                    ->orWhereNull('distance_shops_m')
                    ->orWhereNull('distance_school_m')
                    ->orWhereNull('distance_hospital_m');
            });
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No ads to backfill.');

            return self::SUCCESS;
        }

        $queue = (string) $this->option('queue');
        $sync = (bool) $this->option('sync');

        $this->info(sprintf(
            '%s RecomputeAdDistancesJob for %d ad(s)%s.',
            $sync ? 'Running' : 'Dispatching',
            $ids->count(),
            $sync ? '' : " on queue '{$queue}'",
        ));

        $bar = $this->output->createProgressBar($ids->count());
        $bar->start();

        foreach ($ids as $id) {
            if ($sync) {
                RecomputeAdDistancesJob::dispatchSync((string) $id);
            } else {
                RecomputeAdDistancesJob::dispatch((string) $id)->onQueue($queue);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
