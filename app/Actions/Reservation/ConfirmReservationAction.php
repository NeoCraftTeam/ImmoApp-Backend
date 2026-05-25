<?php

declare(strict_types=1);

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Events\Reservation\ReservationStatusChanged;
use App\Models\TentativeReservation;
use Illuminate\Support\Facades\Log;

/**
 * Confirms a pending viewing reservation. {@see TentativeReservationObserver}
 * notifies the client (mail + database + optional Web Push).
 *
 * Extracted from ViewingReservationController::confirm() to follow the
 * Action pattern established in app/Actions/ and keep the controller thin.
 */
final class ConfirmReservationAction
{
    public function execute(TentativeReservation $reservation): TentativeReservation
    {
        $previous = $reservation->status->value;

        $reservation->update(['status' => ReservationStatus::Confirmed]);

        Log::info('Réservation #'.$reservation->id.' confirmée par le propriétaire.');

        ReservationStatusChanged::dispatch($reservation->load('ad'), $previous);

        return $reservation;
    }
}
