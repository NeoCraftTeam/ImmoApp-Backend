<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\PersonalAccessToken;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Observers\AdObserver;
use App\Observers\PaymentObserver;
use App\Observers\TentativeReservationObserver;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use App\Services\ReservationService;
use App\Services\ViewingScheduleService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

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
        $this->configureRateLimiting();
        $this->ensureLivewireTmpDirectoryExists();

        // Prevent N+1 queries in dev/testing — throws exception on lazy loading
        Model::preventLazyLoading(!app()->isProduction());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Payment::observe(PaymentObserver::class);
        Ad::observe(AdObserver::class);
        TentativeReservation::observe(TentativeReservationObserver::class);
        Activity::observe(ActivityObserver::class);

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
                Log::debug('PASSWORD RESET LINK: '.$link);
            }

            return $link;
        });

        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $domain = match (true) {
                $notifiable->role === UserRole::ADMIN => config('filament.panels.admin_domain'),
                $notifiable->type === UserType::AGENCY => config('filament.panels.agency_domain'),
                $notifiable->type === UserType::INDIVIDUAL => config('filament.panels.owner_domain'),
                default => config('filament.panels.admin_domain'),
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

        Gate::define('viewPulse', fn (?User $user = null) => $user?->isAdmin() ?? false);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            if (!$user) {
                return Limit::perMinute(60)->by($request->ip());
            }

            return match ($user->role) {
                UserRole::ADMIN => Limit::none(),
                UserRole::AGENT => ($user->agency?->hasActiveSubscription() ?? false)
                    ? Limit::perMinute(500)->by($user->id)
                    : Limit::perMinute(300)->by($user->id),
                default => Limit::perMinute(120)->by($user->id),
            };
        });

        // Auth named limiters (values configurable via config/rate_limiting.php)
        RateLimiter::for('auth.register', fn (Request $r) => Limit::perMinute(config('rate_limiting.auth.register', 5))->by($r->ip()));

        RateLimiter::for('auth.login', fn (Request $r) => Limit::perMinute(config('rate_limiting.auth.login', 5))->by($r->ip()));

        RateLimiter::for('auth.resend-verify', fn (Request $r) => Limit::perMinutes(5, config('rate_limiting.auth.resend_verify', 2))->by($r->ip()));

        RateLimiter::for('auth.verify-email', fn (Request $r) => Limit::perMinutes(10, config('rate_limiting.auth.verify_email', 5))->by($r->ip()));

        RateLimiter::for('auth.verify-otp', fn (Request $r) => Limit::perMinute(config('rate_limiting.auth.verify_otp', 5))->by($r->ip()));

        RateLimiter::for('auth.password-reset', fn (Request $r) => Limit::perMinutes(10, config('rate_limiting.auth.password_reset', 3))->by($r->ip()));

        RateLimiter::for('auth.social', fn (Request $r) => Limit::perMinute(config('rate_limiting.auth.social_auth', 10))->by($r->ip()));

        RateLimiter::for('auth.clerk', fn (Request $r) => Limit::perMinute(config('rate_limiting.auth.clerk', 10))->by($r->ip()));

        RateLimiter::for('auth.clerk-otp', fn (Request $r) => Limit::perMinute(config('rate_limiting.auth.clerk_otp', 5))->by($r->ip()));

        RateLimiter::for('auth.update-password', fn (Request $r) => Limit::perMinutes(10, config('rate_limiting.auth.update_password', 5))->by($r->ip()));

        RateLimiter::for('auth.general', fn (Request $r) => Limit::perMinute(config('rate_limiting.auth.general', 30))->by(optional($r->user())->id ?? $r->ip()));

        // Payment named limiters
        RateLimiter::for('payments.initiate', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.initiate', 5))->by(optional($r->user())->id ?? $r->ip()));

        RateLimiter::for('payments.verify', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.verify', 30))->by(optional($r->user())->id ?? $r->ip()));

        RateLimiter::for('payments.cancel', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.cancel', 10))->by(optional($r->user())->id ?? $r->ip()));

        RateLimiter::for('payments.webhook', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.webhook', 120))->by($r->ip()));

        RateLimiter::for('payments.history', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.history', 60))->by(optional($r->user())->id ?? $r->ip()));
    }

    private function ensureLivewireTmpDirectoryExists(): void
    {
        $tmpDisk = config('livewire.temporary_file_upload.disk', 'tmp');
        if ($tmpDisk !== 'tmp') {
            return;
        }

        $dir = storage_path('app/tmp/livewire-tmp');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
