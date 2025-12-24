<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AgencyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Agency $agency): bool
    {
        return $user->isAdmin() || $agency->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAnAgency();
    }

    public function update(User $user, Agency $agency): bool
    {
        return $user->isAdmin() || $agency->owner_id === $user->id;
    }

    public function delete(User $user, Agency $agency): bool
    {
        return $user->isAnAgency();
    }

    public function restore(User $user, Agency $agency): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Agency $agency): bool
    {
        return $user->isAdmin();
    }
}
