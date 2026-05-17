<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\AdminPermission;

/**
 * Provides granular admin-permission checks for the User model.
 *
 * A super-admin (is_super_admin = true OR admin_permissions = null) bypasses
 * every granular check. Non-admin users always return false.
 *
 * @property bool $is_super_admin
 * @property list<string>|null $admin_permissions
 */
trait HasAdminPermissions
{
    /**
     * Returns true if the user holds the Admin role.
     *
     * Implemented by the model — declared here as an abstract contract so
     * IDEs and static analysis tools know this trait depends on it.
     */
    abstract public function isAdmin(): bool;

    /**
     * Super-admin status. A super-admin bypasses every granular admin permission check.
     *
     * Backward compatibility: if `admin_permissions` is `null`, the user is treated as
     * super-admin (this preserves access for every existing administrator).
     */
    public function isSuperAdmin(): bool
    {
        if (!$this->isAdmin()) {
            return false;
        }

        return (bool) $this->is_super_admin || $this->admin_permissions === null;
    }

    /**
     * Check whether the admin has a given granular permission.
     *
     * Non-admins always return `false`. Super-admins always return `true`.
     */
    public function hasAdminPermission(AdminPermission|string $permission): bool
    {
        if (!$this->isAdmin()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $value = $permission instanceof AdminPermission ? $permission->value : $permission;

        return in_array($value, (array) ($this->admin_permissions ?? []), true);
    }

    /**
     * Check whether the admin has at least one of the supplied permissions.
     *
     * @param  iterable<AdminPermission|string>  $permissions
     */
    public function hasAnyAdminPermission(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasAdminPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
