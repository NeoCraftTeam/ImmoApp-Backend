<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TentativeReservation;
use App\Models\User;

class TentativeReservationPolicy
{
    /**
     * A client can cancel only their own reservation.
     * A landlord can cancel any reservation on one of their properties.
     */
    public function cancel(User $user, TentativeReservation $reservation): bool
    {
        return $reservation->isOwnedByClient($user) || $reservation->isOwnedByLandlord($user);
    }
}
