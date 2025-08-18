<?php

namespace App\Policies;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdPolicy
{
    use HandlesAuthorization;

    public function viewAny(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canPublishAds();
    }

    public function update(User $user, Ad $ad): bool
    {
        return ($user->isAgent() && ($user->isAnAgency() || $user->isAnIndividual()))
            && $user->id === $ad->user_id;
    }

    public function delete(User $user, Ad $ad): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isAgent() && ($user->isAnAgency() || $user->isAnIndividual()) && $user->id === $ad->user_id;
    }

    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }
}
