<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->agency_id !== null && $user->agency_id === $subscription->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isAgent() && $user->agency_id !== null;
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return false;
    }
}
