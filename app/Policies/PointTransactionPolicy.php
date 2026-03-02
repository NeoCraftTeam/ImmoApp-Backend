<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PointTransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function view(User $user, PointTransaction $pointTransaction): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $pointTransaction->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PointTransaction $pointTransaction): bool
    {
        return false;
    }

    public function delete(User $user, PointTransaction $pointTransaction): bool
    {
        return false;
    }

    public function restore(User $user, PointTransaction $pointTransaction): bool
    {
        return false;
    }

    public function forceDelete(User $user, PointTransaction $pointTransaction): bool
    {
        return false;
    }
}
