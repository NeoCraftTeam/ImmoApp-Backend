<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notification\RetentionPushService;
use Illuminate\Console\Command;

/**
 * Runs all behavioral retention push triggers in sequence.
 *
 * Triggers (all frequency-capped via Redis):
 *   win_back             — dormant users (no login ≥ 3 days) with push subscription
 *   price_drop           — a favorited ad's price dropped by ≥ 5 000 FCFA
 *   viewing_reminder     — confirmed viewing slot is tomorrow
 *   lease_expiry         — lease contract ends in 30 or 7 days
 *
 * Run manually:   php artisan app:send-retention-pushes
 * Run dry-run:    php artisan app:send-retention-pushes --dry-run
 */
final class SendRetentionPushes extends Command
{
    protected $signature = 'app:send-retention-pushes {--dry-run : Show trigger targets without actually sending}';

    protected $description = 'Send behavioral retention push notifications (win-back, price drop, viewing reminders, lease expiry)';

    public function handle(RetentionPushService $service): int
    {
        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] No pushes will be sent — remove --dry-run to send for real.');
        }

        $this->info('🔔 Retention push notifications starting…');

        $triggers = [
            'win_back' => $service->winBackDormantUsers(...),
            'price_drops' => $service->notifyPriceDropOnFavorites(...),
            'viewing_reminders' => $service->notifyViewingReminders(...),
            'lease_expiries' => $service->notifyLeaseExpiries(...),
        ];

        $rows = [];
        $total = 0;

        foreach ($triggers as $name => $run) {
            $label = str_replace('_', ' ', $name);
            $this->line("  ↳ {$label}…");

            $count = $this->option('dry-run') ? 0 : $run();

            $rows[] = [$label, $count];
            $total += $count;
        }

        $this->table(['Trigger', 'Sent'], $rows);
        $this->info("✅  Total: {$total} push notification(s) dispatched.");

        return Command::SUCCESS;
    }
}
