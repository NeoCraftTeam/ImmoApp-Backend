<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ad;
use App\Models\User;

class ViewingAvailabilityPolicy
{
    /**
     * The landlord must own the ad to manage its availability.
     */
    public function manage(User $user, Ad $ad): bool
    {
        return $user->id === $ad->user_id;
    }
}
