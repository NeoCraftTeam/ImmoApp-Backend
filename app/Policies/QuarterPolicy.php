<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Quarter;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuarterPolicy
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
        return $user->isAdmin();
    }

    public function update(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Quarter $quarter): bool
    {
        return $user->isAdmin();
    }
}
