<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\ReservationServiceInterface;
use App\Enums\ReservationStatus;
use App\Models\TentativeReservation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Auto-complete confirmed reservations whose slot has ended.
 * Runs hourly. Selects CONFIRMED reservations where
 * CONCAT(slot_date, ' ', slot_ends_at) < NOW() and marks them COMPLETED.
 */
class CompleteStaleReservationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(ReservationServiceInterface $reservationService): void
    {
        $stale = TentativeReservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->with(['ad', 'client'])
            ->get()
            ->filter(function (TentativeReservation $r): bool {
                $endsAt = Carbon::parse(
                    $r->slot_date->toDateString().' '.$r->slot_ends_at,
                    config('app.timezone', 'Africa/Douala'),
                );

                return $endsAt->isPast();
            });

        foreach ($stale as $reservation) {
            $reservationService->complete($reservation);
        }
    }
}
