<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\StalePaymentsDetectedNotification;
use App\Services\Payment\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupStalePaymentsCommand extends Command
{
    protected $signature = 'app:cleanup-stale-payments {--hours=24 : Hours after which a pending payment is considered stale}';

    protected $description = 'Reconcile stale PENDING payments with the gateway, then mark the truly-abandoned ones as FAILED and notify admins';

    public function handle(PaymentService $paymentService): int
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

        // Réconciliation d'abord : un paiement dont le webhook a été manqué peut
        // avoir RÉUSSI côté passerelle. On re-interroge la passerelle avant de
        // marquer échoué — syncPaymentStatus crédite le compte si c'est payé et
        // n'ouvre jamais un état terminal existant (idempotent).
        $reconciled = 0;
        $failedIds = [];

        foreach ($stalePayments as $payment) {
            try {
                $synced = $paymentService->syncPaymentStatus($payment);
            } catch (Throwable $e) {
                Log::warning('Stale payment reconciliation failed', [
                    'payment_id' => $payment->id,
                    'tx_ref' => $payment->transaction_id,
                    'error' => $e->getMessage(),
                ]);
                $synced = $payment->fresh() ?? $payment;
            }

            if ($synced->status === PaymentStatus::SUCCESS) {
                $reconciled++;

                continue;
            }

            if ($synced->status === PaymentStatus::PENDING) {
                $synced->update(['status' => PaymentStatus::FAILED]);
                $failedIds[] = $synced->id;
            }
        }

        $failedCount = count($failedIds);

        Log::warning('Stale payments cleaned up', [
            'reconciled_success' => $reconciled,
            'marked_failed' => $failedCount,
            'cutoff_hours' => $hours,
            'failed_payment_ids' => $failedIds,
        ]);

        if ($failedCount > 0) {
            $admins = User::query()->where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new StalePaymentsDetectedNotification($failedCount, $hours));
            }
        }

        $this->info("Reconciled {$reconciled} as paid, marked {$failedCount} as FAILED (cutoff {$hours}h).");

        return self::SUCCESS;
    }
}
