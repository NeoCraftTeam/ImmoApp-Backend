<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\TentativeReservation;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\ReservationConfirmedClientNotification;
use App\Notifications\ReservationCreatedClientNotification;
use App\Notifications\ReservationCreatedLandlordNotification;
use App\Notifications\ReservationExpiredNotification;

class TentativeReservationObserver
{
    public function created(TentativeReservation $reservation): void
    {
        $reservation->loadMissing(['ad.user', 'client']);

        $reservation->client->notify(new ReservationCreatedClientNotification($reservation));
        $reservation->ad->user->notify(new ReservationCreatedLandlordNotification($reservation));
    }

    public function updated(TentativeReservation $reservation): void
    {
        if (!$reservation->wasChanged('status')) {
            return;
        }

        $reservation->loadMissing(['ad.user', 'client']);

        match ($reservation->status) {
            ReservationStatus::Confirmed => $reservation->client->notify(new ReservationConfirmedClientNotification($reservation)),
            ReservationStatus::Cancelled => $this->notifyCancellation($reservation),
            ReservationStatus::Expired => $this->notifyExpiration($reservation),
            default => null,
        };
    }

    private function notifyCancellation(TentativeReservation $reservation): void
    {
        $reservation->client->notify(new ReservationCancelledNotification($reservation));
        $reservation->ad->user->notify(new ReservationCancelledNotification($reservation));
    }

    private function notifyExpiration(TentativeReservation $reservation): void
    {
        $reservation->client->notify(new ReservationExpiredNotification($reservation));
    }
}
