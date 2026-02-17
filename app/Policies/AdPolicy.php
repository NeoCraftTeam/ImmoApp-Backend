<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Ad $ad): bool
    {
        if ($ad->status === \App\Enums\AdStatus::AVAILABLE) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $user->id === $ad->user_id);
    }

    public function create(User $user): bool
    {
        return $user->canPublishAds();
    }

    public function update(User $user, Ad $ad): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // P0-3 Fix: Allow agents to update their own ads
        return $user->isAgent() && $user->id === $ad->user_id;
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

    public function adsNearby(?User $user): bool
    {
        // Allow all users (including guests and agents) to access nearby ads
        return true;
    }
}
