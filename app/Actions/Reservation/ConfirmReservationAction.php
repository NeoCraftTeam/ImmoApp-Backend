<?php

declare(strict_types=1);

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Models\TentativeReservation;
use App\Notifications\ReservationConfirmedClientNotification;
use Illuminate\Support\Facades\Log;

/**
 * Confirms a pending viewing reservation and notifies the client.
 *
 * Extracted from ViewingReservationController::confirm() to follow the
 * Action pattern established in app/Actions/ and keep the controller thin.
 */
final class ConfirmReservationAction
{
    public function execute(TentativeReservation $reservation): TentativeReservation
    {
        $reservation->update(['status' => ReservationStatus::Confirmed]);
        $reservation->loadMissing('client');
        $reservation->client->notify(new ReservationConfirmedClientNotification($reservation));

        Log::info('Réservation #'.$reservation->id.' confirmée par le propriétaire.');

        return $reservation;
    }
}
