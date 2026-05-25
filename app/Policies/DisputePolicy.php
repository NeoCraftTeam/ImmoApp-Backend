<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Enums\DisputeStatus;
use App\Enums\UserRole;
use App\Models\Dispute;
use App\Models\User;

class DisputePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // every authenticated user can list their own disputes
    }

    public function view(User $user, Dispute $dispute): bool
    {
        return $this->isPartyOrAdmin($user, $dispute);
    }

    public function create(User $user): bool
    {
        return $user->is_active ?? true;
    }

    public function reply(User $user, Dispute $dispute): bool
    {
        if (!$dispute->status->isOpen()) {
            return false;
        }

        return $this->isPartyOrAdmin($user, $dispute);
    }

    public function uploadEvidence(User $user, Dispute $dispute): bool
    {
        return $this->reply($user, $dispute);
    }

    public function transition(User $user, Dispute $dispute): bool
    {
        return $this->isAdmin($user);
    }

    public function adminAccess(User $user): bool
    {
        return $this->isAdmin($user);
    }

    private function isPartyOrAdmin(User $user, Dispute $dispute): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $dispute->isParty($user->id);
    }

    private function isAdmin(User $user): bool
    {
        if (($user->role ?? null) !== UserRole::ADMIN) {
            return false;
        }

        // Granular permission with super-admin bypass (admin_permissions = null).
        return $user->hasAdminPermission(AdminPermission::DisputesManage);
    }

    /**
     * Status transitions can only be requested by admins via this policy.
     * Returns whether the requested target is reachable from the current state.
     */
    public function canTransitionTo(User $user, Dispute $dispute, DisputeStatus $target): bool
    {
        if (!$this->isAdmin($user)) {
            return false;
        }

        return $dispute->status->canTransitionTo($target);
    }
}
