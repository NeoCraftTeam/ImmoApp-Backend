<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnlockedAdPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, UnlockedAd $unlockedAd): bool
    {
        return $user->isAdmin() || ($user->isCustomer() && ($user->id === $unlockedAd->user_id));
    }

    public function create(User $user): bool
    {
        // Assuming only customers can create unlocked ads
        return $user->isCustomer();
    }

    public function update(User $user, UnlockedAd $unlockedAd): bool
    {
        return false;
    }

    public function delete(User $user, UnlockedAd $unlockedAd): bool
    {
        return false;
    }

    public function restore(User $user, UnlockedAd $unlockedAd): bool
    {
        return false;
    }

    public function forceDelete(User $user, UnlockedAd $unlockedAd): bool
    {
        return false;
    }
}
