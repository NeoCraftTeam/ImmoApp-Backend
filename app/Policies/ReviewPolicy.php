<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReviewPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // ?User $user allows this method to be called without a user, e.g., for public access
       return true;
    }

    public function view(User $user, Review $review): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, Review $review): bool
    {
        // Only the author of the review can update it
        return $user->id === $review->user_id;
    }

    public function delete(User $user, Review $review): bool
    {
        return false;
    }

    public function restore(User $user, Review $review): bool
    {
        return false;
    }

    public function forceDelete(User $user, Review $review): bool
    {
        return false;
    }
}
