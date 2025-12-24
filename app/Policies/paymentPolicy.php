<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Regular users can only view their own payments
        return $user->id === $payment->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->isAgent() && ($user->isAnAgency() || $user->isAnIndividual());
    }

    public function update(User $user, Payment $payment): bool
    {
        return false; // No updates allowed for payments
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false; // No deletions allowed for payments
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false; // No restoration allowed for payments
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false; // No force deletions allowed for payments
    }
}
