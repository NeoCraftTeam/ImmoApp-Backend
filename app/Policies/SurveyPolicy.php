<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Survey;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyPolicy
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

    public function update(User $user, Survey $survey): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Survey $survey): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Survey $survey): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Survey $survey): bool
    {
        return $user->isAdmin();
    }
}
