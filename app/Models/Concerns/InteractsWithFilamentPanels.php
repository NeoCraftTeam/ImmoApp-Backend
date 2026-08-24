<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Filament contract methods for the User model: panel access, tenant (agency)
 * resolution, and the display name / avatar URL shown in the admin UI. Depends
 * on the role/type predicates provided by {@see HasRolesAndType} (notably
 * `isAdmin()`).
 *
 * @property UserRole $role
 * @property UserType|null $type
 * @property string $firstname
 * @property string $lastname
 * @property string $avatar
 * @property string|null $agency_id
 * @property-read Agency|null $agency
 */
trait InteractsWithFilamentPanels
{
    /**
     * Determine whether this user may access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $panelId = $panel->getId();

        if ($panelId === 'agency') {
            return $this->role === UserRole::AGENT && $this->type === UserType::AGENCY;
        }

        if ($panelId === 'bailleur') {
            return $this->role === UserRole::AGENT && $this->type === UserType::INDIVIDUAL;
        }

        return false;
    }

    /**
     * Return the tenants (agencies) accessible to this user for the given panel.
     *
     * @return Collection<int, Agency>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isAdmin()) {
            return Agency::all();
        }

        return collect([$this->agency])->filter();
    }

    /**
     * Determine whether this user can access a specific tenant.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->agency_id === $tenant->getKey();
    }

    /**
     * Return the user's display name for Filament UI.
     */
    public function getFilamentName(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    /**
     * Return a publicly accessible avatar URL for the Filament UI.
     *
     * Returns null (letting Filament render its placeholder) when no avatar
     * is stored or the file no longer exists on disk.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if (str_starts_with($this->avatar ?? '', 'http')) {
            return $this->avatar;
        }

        $disk = config('filesystems.app_media_disk');

        if ($this->avatar && Storage::disk($disk)->exists($this->avatar)) {
            return Storage::disk($disk)->url($this->avatar);
        }

        // Privacy: Return null to let Filament/Frontend handle the default placeholder.
        return null;
    }
}
