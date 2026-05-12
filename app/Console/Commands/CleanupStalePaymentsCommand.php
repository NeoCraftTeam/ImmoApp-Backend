<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\StalePaymentsDetectedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStalePaymentsCommand extends Command
{
    protected $signature = 'app:cleanup-stale-payments {--hours=24 : Hours after which a pending payment is considered stale}';

    protected $description = 'Mark stale PENDING payments as FAILED and notify admins';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $stalePayments = Payment::query()
            ->where('status', PaymentStatus::PENDING)
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($stalePayments->isEmpty()) {
            $this->info('No stale payments found.');

            return self::SUCCESS;
        }

        $count = $stalePayments->count();

        Payment::query()
            ->where('status', PaymentStatus::PENDING)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => PaymentStatus::FAILED]);

        Log::warning('Stale payments cleaned up', [
            'count' => $count,
            'cutoff_hours' => $hours,
            'payment_ids' => $stalePayments->pluck('id')->toArray(),
        ]);

        $admins = User::query()->where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new StalePaymentsDetectedNotification($count, $hours));
        }

        $this->info("Marked {$count} stale payments as FAILED and notified admins.");

        return self::SUCCESS;
    }
}
