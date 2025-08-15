<?php

namespace App\Policies;

use App\Models\AdImage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdImagePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AdImage $adImage): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAgent() && ($user->isAnAgency() || $user->isAnIndividual());
    }

    public function update(User $user, AdImage $adImage): bool
    {
        return $user->isAgent() && ($user->isAnAgency() || $user->isAnIndividual())
            && $user->id === $adImage->ad->user_id;
    }

    public function delete(User $user, AdImage $adImage): bool
    {
        return ($user->isAdmin() || ($user->isAgent() && ($user->isAnAgency() || $user->isAnIndividual())))
            && $user->id === $adImage->ad->user_id;
    }

    public function restore(User $user, AdImage $adImage): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, AdImage $adImage): bool
    {
        return $user->isAdmin();
    }
}
