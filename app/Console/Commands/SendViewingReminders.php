<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\TentativeReservation;
use App\Notifications\ViewingReminderLandlordNotification;
use App\Notifications\ViewingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Send J-1 viewing reminders to both CLIENT and LANDLORD before their scheduled visit.
 *
 * Runs daily at 08:00. Selects all pending/confirmed reservations for tomorrow
 * and notifies each party once (tracked via notified_at / landlord_notified_at).
 */
class SendViewingReminders extends Command
{
    protected $signature = 'app:send-viewing-reminders
                            {--dry-run : Print reservations without sending notifications}';

    protected $description = 'Send J-1 reminder notifications to clients and landlords before their scheduled property viewing.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $tomorrow = now()->addDay()->toDateString();

        $reservations = TentativeReservation::query()
            ->with(['client', 'ad.user'])
            ->whereDate('slot_date', $tomorrow)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->where(function ($q): void {
                $q->whereNull('notified_at')
                    ->orWhereNull('landlord_notified_at');
            })
            ->get();

        $sent = 0;

        foreach ($reservations as $reservation) {
            /** @phpstan-ignore-next-line booleanNot.alwaysFalse, booleanOr.alwaysFalse */
            if (!$reservation->client || !$reservation->ad) {
                continue;
            }

            if ($isDryRun) {
                $this->line(sprintf(
                    'DRY-RUN: Would remind client %s + landlord %s — "%s" on %s at %s',
                    $reservation->client->email,
                    $reservation->ad->user->email,
                    $reservation->ad->title,
                    $reservation->slot_date->toDateString(),
                    substr((string) $reservation->slot_starts_at, 0, 5),
                ));

                continue;
            }

            $updates = [];

            if (is_null($reservation->notified_at)) {
                try {
                    $reservation->client->notify(new ViewingReminderNotification($reservation));
                    $updates['notified_at'] = now();
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error('SendViewingReminders: failed to notify client', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (is_null($reservation->landlord_notified_at) && $reservation->ad->user) {
                try {
                    $reservation->ad->user->notify(new ViewingReminderLandlordNotification($reservation));
                    $updates['landlord_notified_at'] = now();
                } catch (\Throwable $e) {
                    Log::error('SendViewingReminders: failed to notify landlord', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($updates !== []) {
                $reservation->update($updates);
            }
        }

        $this->info("Viewing reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
