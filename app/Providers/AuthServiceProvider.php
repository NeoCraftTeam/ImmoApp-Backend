<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Boot the application services to define authorization gates that determine user access
     * based on their roles.
     */
    public function boot(): void
    {
        Gate::define('admin-access', fn ($user) => $user->role === UserRole::ADMIN);

        Gate::define('customer-access', fn ($user) => $user->role === UserRole::CUSTOMER);

        Gate::define('agent-access', fn ($user) => $user->role === UserRole::AGENT);
    }
}
