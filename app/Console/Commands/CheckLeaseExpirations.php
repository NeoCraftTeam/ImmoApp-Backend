<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LeaseContract;
use App\Notifications\LeaseExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Item 16: Send renewal reminders for leases expiring in 90, 60, 30, 14, and 7 days.
 */
class CheckLeaseExpirations extends Command
{
    protected $signature = 'app:check-lease-expirations
                            {--dry-run : Print contracts without sending notifications}';

    protected $description = 'Notify landlords about leases expiring in the next 90 days.';

    /** @var array<int, int> */
    private const array REMINDER_THRESHOLDS = [90, 60, 30, 14, 7];

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $sent = 0;

        foreach (self::REMINDER_THRESHOLDS as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $contracts = LeaseContract::query()
                ->with(['user', 'ad'])
                ->whereDate('lease_end', $targetDate)
                ->get();

            foreach ($contracts as $contract) {
                if (!$contract->user) {
                    continue;
                }

                if ($isDryRun) {
                    $this->line("DRY-RUN: Would notify {$contract->user->email} — lease {$contract->id} expires in {$days} day(s).");

                    continue;
                }

                try {
                    $contract->user->notify(new LeaseExpiringNotification($contract, $days));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error('CheckLeaseExpirations: failed to notify', [
                        'contract_id' => $contract->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Lease expiration reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
