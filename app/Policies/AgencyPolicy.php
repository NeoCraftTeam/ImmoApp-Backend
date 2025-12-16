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
        return $user->isAdmin() || $agency->user_id === $user->id;
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, agency $agency): bool
    {
    }

    public function delete(User $user, agency $agency): bool
    {
    }

    public function restore(User $user, agency $agency): bool
    {
    }

    public function forceDelete(User $user, agency $agency): bool
    {
    }
}
