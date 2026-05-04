<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\AdminPermission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[\Override]
    public function register(): void {}

    /**
     * Boot the application services to define authorization gates that determine user access
     * based on their roles.
     */
    public function boot(): void
    {
        Gate::define('admin-access', fn (?User $user) => $user?->role === UserRole::ADMIN);
        Gate::define('customer-access', fn (?User $user) => $user?->role === UserRole::CUSTOMER);
        Gate::define('agent-access', fn (?User $user) => $user?->role === UserRole::AGENT);

        // Super-admin shortcut: ignored if a more specific gate is hit.
        Gate::before(function (?User $user, string $ability): ?bool {
            if (!str_starts_with($ability, 'admin.')) {
                return null;
            }

            return $user?->isSuperAdmin() ? true : null;
        });

        // Granular admin gates — one per AdminPermission case, named "admin.<value>".
        foreach (AdminPermission::cases() as $permission) {
            Gate::define(
                'admin.'.$permission->value,
                fn (?User $user) => $user?->hasAdminPermission($permission) ?? false,
            );
        }
    }
}
