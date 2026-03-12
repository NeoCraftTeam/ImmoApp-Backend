<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\PersonalAccessToken;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use App\Services\ReservationService;
use App\Services\ViewingScheduleService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        $this->app->bind(ViewingScheduleServiceInterface::class, ViewingScheduleService::class);
        $this->app->bind(ReservationServiceInterface::class, ReservationService::class);
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
        \App\Models\TentativeReservation::observe(\App\Observers\TentativeReservationObserver::class);
        \Spatie\Activitylog\Models\Activity::observe(\App\Observers\ActivityObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Partage le logo encodé en base64 avec toutes les vues emails.* (y compris sous-dossiers)
        View::composer(['emails.*', 'emails.reservation.*'], function ($view): void {
            $logoPath = public_path('images/keyhomelogo_transparent.png');
            $view->with('emailLogoBase64', file_exists($logoPath)
                ? base64_encode((string) file_get_contents($logoPath))
                : ''
            );
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $link = "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->getEmailForVerification()}";

            if (app()->isLocal()) {
                \Illuminate\Support\Facades\Log::debug('PASSWORD RESET LINK: '.$link);
            }

            return $link;
        });

        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $domain = match (true) {
                $notifiable->role === UserRole::ADMIN => config('filament.domains.admin_domain'),
                $notifiable->type === UserType::AGENCY => config('filament.domains.agency_domain'),
                $notifiable->type === UserType::INDIVIDUAL => config('filament.domains.owner_domain'),
                default => config('filament.domains.admin_domain'),
            };

            $rootUrl = $domain ? "https://{$domain}" : config('app.url');

            URL::forceRootUrl($rootUrl);

            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
            );

            URL::forceRootUrl(config('app.url'));

            return $verificationUrl;
        });

        Gate::define('viewPulse', fn (?\App\Models\User $user = null) => $user?->isAdmin() ?? false);
    }
}
