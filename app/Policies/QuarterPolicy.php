<?php

namespace App\Policies;

use App\Models\Quarter;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuarterPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quarter $quarter): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin() || $user->id === $quarter->city->user_id;
    }

    public function delete(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin() || $user->id === $quarter->city->user_id;
    }

    public function restore(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin() || $user->id === $quarter->city->user_id;
    }

    public function forceDelete(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin() || $user->id === $quarter->city->user_id;
    }
}
