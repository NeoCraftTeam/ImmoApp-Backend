<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LeaseAuditEvent;
use App\Enums\LeaseStatus;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureAuditLog;
use Illuminate\Console\Command;

/**
 * Daily sweep that flips Active leases whose `lease_end` is in the past
 * to {@see LeaseStatus::Expired}. Keeps the dashboard occupancy KPI
 * accurate and ensures expired leases stop accruing monthly rent without
 * landlord intervention.
 *
 * Reminders for upcoming expirations are handled separately by
 * {@see CheckLeaseExpirations}; this command only mutates state.
 */
final class ExpireOverdueLeases extends Command
{
    protected $signature = 'app:expire-overdue-leases
                            {--dry-run : Print affected leases without updating them}';

    protected $description = 'Flip Active leases past their lease_end to Expired (idempotent).';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $today = now()->toDateString();

        $contracts = LeaseContract::query()
            ->where('status', LeaseStatus::Active->value)
            ->whereDate('lease_end', '<', $today)
            ->get();

        if ($contracts->isEmpty()) {
            $this->info('No overdue leases to expire.');

            return self::SUCCESS;
        }

        foreach ($contracts as $contract) {
            if ($isDryRun) {
                $this->line("DRY-RUN: Would expire lease {$contract->contract_number} (ended {$contract->lease_end?->toDateString()}).");

                continue;
            }

            $contract->update(['status' => LeaseStatus::Expired->value]);

            LeaseSignatureAuditLog::record(
                leaseContractId: $contract->id,
                event: LeaseAuditEvent::Expired,
                userId: null,
                metadata: ['lease_end' => $contract->lease_end?->toDateString()],
            );
        }

        $count = $contracts->count();
        $this->info("Expired {$count} overdue lease(s).");

        return self::SUCCESS;
    }
}
