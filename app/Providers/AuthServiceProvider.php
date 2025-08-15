<?php

namespace App\Providers;

use App\UserRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
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
        Gate::define('admin-access', function ($user) {
            return $user->role === UserRole::ADMIN;
        });

        Gate::define('customer-access', function ($user) {
            return $user->role === UserRole::CUSTOMER;
        });

        Gate::define('agent-access', function ($user) {
            return $user->role === UserRole::AGENT;
        });
    }
}
