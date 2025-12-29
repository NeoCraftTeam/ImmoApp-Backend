<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdTypePolicy
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
