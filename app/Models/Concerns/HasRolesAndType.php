<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Enums\UserType;
use Illuminate\Support\Carbon;

/**
 * Role- and type-based predicates for the User model: the read-only questions
 * ("is this an agent?", "may they publish ads?", "which panel/session prefix?")
 * derived purely from the `role` and `type` columns. Complements
 * {@see HasAdminPermissions}, which builds granular admin checks on top of the
 * `isAdmin()` provided here.
 *
 * @property UserRole $role
 * @property UserType|null $type
 * @property Carbon|null $must_change_password_at
 */
trait HasRolesAndType
{
    /**
     * Returns true if the user may publish ads (agent or admin).
     */
    public function canPublishAds(): bool
    {
        return in_array($this->role, [UserRole::AGENT, UserRole::ADMIN]);
    }

    /**
     * Returns true if the user holds the Agent role.
     */
    public function isAgent(): bool
    {
        return $this->role === UserRole::AGENT;
    }

    /**
     * Integrated Next.js owner panel (/owner/*) — AGENT only; admins use Filament.
     */
    public function mayAccessOwnerPanel(): bool
    {
        return $this->isAgent();
    }

    /**
     * Sanctum token name prefix for API session isolation (owner vs client).
     */
    public function sanctumSessionPrefix(): string
    {
        return $this->isAgent() ? 'owner' : 'client';
    }

    /**
     * Returns true if the user holds the Admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Returns true if the user holds the Customer role.
     */
    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    /**
     * Returns true if the user's type is Individual.
     */
    public function isAnIndividual(): bool
    {
        return $this->type === UserType::INDIVIDUAL;
    }

    /**
     * Returns true if the user's type is Agency.
     */
    public function isAnAgency(): bool
    {
        return $this->type === UserType::AGENCY;
    }

    /**
     * Returns true when the user must change their password on next login.
     */
    public function hasMustChangePassword(): bool
    {
        return $this->must_change_password_at !== null;
    }
}
