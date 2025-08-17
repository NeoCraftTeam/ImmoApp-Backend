<?php

namespace App\Policies;

use App\Models\AdType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
                return true;
    }

    public function view(User $user, AdType $adType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AdType $adType): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AdType $adType): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, AdType $adType): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, AdType $adType): bool
    {
        return $user->isAdmin();
    }
}
