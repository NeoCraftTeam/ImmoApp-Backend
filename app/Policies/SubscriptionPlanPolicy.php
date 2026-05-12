<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, SubscriptionPlan $subscriptionPlan): bool
    {
        return false;
    }
}
