<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\CancelledBy;
use App\Enums\ReservationStatus;
use App\Models\TentativeReservation;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\ReservationCompletedClientNotification;
use App\Notifications\ReservationConfirmedClientNotification;
use App\Notifications\ReservationConfirmedLandlordNotification;
use App\Notifications\ReservationCreatedClientNotification;
use App\Notifications\ReservationCreatedLandlordNotification;
use App\Notifications\ReservationExpiredNotification;
use App\Notifications\ReservationNoShowLandlordNotification;
use Illuminate\Support\Facades\Cache;

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

        // Invalidate trust scores for both parties on status changes
        $this->invalidateTrustScores($reservation);

        match ($reservation->status) {
            ReservationStatus::Confirmed => $this->notifyConfirmation($reservation),
            ReservationStatus::Cancelled => $this->notifyCancellation($reservation),
            ReservationStatus::Expired => $this->notifyExpiration($reservation),
            ReservationStatus::Completed => $reservation->client->notify(new ReservationCompletedClientNotification($reservation)),
            ReservationStatus::NoShow => $reservation->ad->user->notify(new ReservationNoShowLandlordNotification($reservation)),
            default => null,
        };
    }

    private function notifyConfirmation(TentativeReservation $reservation): void
    {
        $reservation->client->notify(new ReservationConfirmedClientNotification($reservation));
        $reservation->ad->user->notify(new ReservationConfirmedLandlordNotification($reservation));
    }

    private function notifyCancellation(TentativeReservation $reservation): void
    {
        match ($reservation->cancelled_by) {
            CancelledBy::Landlord => $reservation->client->notify(new ReservationCancelledNotification($reservation)),
            CancelledBy::Client => $reservation->ad->user->notify(new ReservationCancelledNotification($reservation)),
            default => $this->notifyCancellationBothParties($reservation),
        };
    }

    private function notifyCancellationBothParties(TentativeReservation $reservation): void
    {
        $reservation->client->notify(new ReservationCancelledNotification($reservation));
        $reservation->ad->user->notify(new ReservationCancelledNotification($reservation));
    }

    private function notifyExpiration(TentativeReservation $reservation): void
    {
        $reservation->client->notify(new ReservationExpiredNotification($reservation));
    }

    private function invalidateTrustScores(TentativeReservation $reservation): void
    {
        $client = $reservation->client;
        if ($client->trust_score_consent) {
            Cache::forget("trust_score:{$client->id}:tenant");
        }

        $landlord = $reservation->ad->user ?? null;
        if ($landlord?->trust_score_consent) {
            Cache::forget("trust_score:{$landlord->id}:landlord");
        }
    }
}
