<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent N+1 queries in dev/testing — throws exception on lazy loading
        Model::preventLazyLoading(!app()->isProduction());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\Ad::observe(\App\Observers\AdObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $link = "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->getEmailForVerification()}";

            if (app()->isLocal()) {
                \Illuminate\Support\Facades\Log::debug('PASSWORD RESET LINK: '.$link);
            }

            return $link;
        });

        Gate::define('viewPulse', fn (?\App\Models\User $user = null) => $user?->isAdmin() ?? false);
    }
}
