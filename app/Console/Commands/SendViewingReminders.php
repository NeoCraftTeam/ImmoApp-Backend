<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\TentativeReservation;
use App\Notifications\ViewingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Audit Item 5 — send J-1 viewing reminder to clients before their scheduled visit.
 *
 * Runs daily at 08:00. Selects all pending/confirmed reservations for tomorrow
 * whose notified_at is NULL (reminder not yet sent), notifies the client, and
 * marks notified_at to prevent duplicate sends.
 */
class SendViewingReminders extends Command
{
    protected $signature = 'app:send-viewing-reminders
                            {--dry-run : Print reservations without sending notifications}';

    protected $description = 'Send J-1 reminder notifications to clients before their scheduled property viewing.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $tomorrow = now()->addDay()->toDateString();

        $reservations = TentativeReservation::query()
            ->with(['client', 'ad'])
            ->whereDate('slot_date', $tomorrow)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereNull('notified_at')
            ->get();

        $sent = 0;

        foreach ($reservations as $reservation) {
            /** @phpstan-ignore-next-line booleanNot.alwaysFalse, booleanOr.alwaysFalse */
            if (!$reservation->client || !$reservation->ad) {
                continue;
            }

            if ($isDryRun) {
                $this->line(sprintf(
                    'DRY-RUN: Would remind %s — "%s" on %s at %s',
                    $reservation->client->email,
                    $reservation->ad->title,
                    $reservation->slot_date->toDateString(),
                    substr((string) $reservation->slot_starts_at, 0, 5),
                ));

                continue;
            }

            try {
                $reservation->client->notify(new ViewingReminderNotification($reservation));
                $reservation->update(['notified_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('SendViewingReminders: failed to notify', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Viewing reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
