<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AiSearchServiceInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Contracts\RecommendationEngineInterface;
use App\Contracts\StripeSavedCardServiceInterface;
use App\Contracts\TrustScoreServiceInterface;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\CashierSubscription;
use App\Models\CashierSubscriptionItem;
use App\Models\Payment;
use App\Models\PersonalAccessToken;
use App\Models\PointPackage;
use App\Models\SubscriptionPlan;
use App\Models\TentativeReservation;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Observers\AdObserver;
use App\Observers\PaymentObserver;
use App\Observers\PointPackageObserver;
use App\Observers\SubscriptionPlanObserver;
use App\Observers\TentativeReservationObserver;
use App\Observers\UserObserver;
use App\Services\AiSearchService;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use App\Services\Payment\FlutterwavePaymentService;
use App\Services\Payment\PaymentMethodGateService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use App\Services\RecommendationEngine;
use App\Services\ReservationService;
use App\Services\TrustScoreService;
use App\Services\ViewingScheduleService;
use App\Services\WebAuthn\CacheChallengeRepository;
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
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;
use Laravel\Cashier\Cashier;
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
        $this->app->bind(AiSearchServiceInterface::class, AiSearchService::class);
        $this->app->bind(RecommendationEngineInterface::class, RecommendationEngine::class);
        $this->app->bind(TrustScoreServiceInterface::class, TrustScoreService::class);
        $this->app->bind(StripeSavedCardServiceInterface::class, StripePaymentService::class);

        // WebAuthn: use cache (Redis) for challenge storage instead of session.
        // The default SessionChallengeRepository breaks with SESSION_DRIVER=cookie
        // because the challenge data exceeds the 4 KB browser cookie size limit.
        $this->app->bind(WebAuthnChallengeRepository::class, CacheChallengeRepository::class);

        // Admin-controlled gating of every payment method. Singleton because
        // the runtime overrides are cached per-method and we want a single
        // source of truth across the whole request lifecycle.
        $this->app->singleton(PaymentMethodGateService::class);

        $this->app->singleton(PaymentService::class, function ($app): PaymentService {
            $defaultName = (string) config('payment.default', 'flutterwave');
            $fallbackName = config('payment.fallback');

            $gateway = $this->resolvePaymentGateway($app, $defaultName);
            $fallback = $fallbackName ? $this->resolvePaymentGateway($app, (string) $fallbackName) : null;

            // Registry of every gateway available at runtime, keyed by the
            // value returned by `getName()` (matches PaymentGateway enum).
            // PaymentService routes a request via
            // `PaymentMethod::gateway()->value` lookup.
            $registry = [
                $gateway->getName() => $gateway,
            ];
            if ($fallback !== null) {
                $registry[$fallback->getName()] = $fallback;
            }
            // Always register Stripe so card payments work even when the
            // default gateway is Flutterwave.
            $stripe = $app->make(StripePaymentService::class);
            $registry[$stripe->getName()] = $stripe;

            return new PaymentService($gateway, $fallback, $registry);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->ensureLivewireTmpDirectoryExists();

        // Cashier model overrides — `subscriptions` was renamed to
        // `cashier_subscriptions` in `database/migrations/..._create_subscriptions_table.php`
        // to avoid collision with the existing business `subscriptions`
        // (App\Models\Subscription, agency plans). The custom subclasses
        // pin the table name; nothing else changes in the Cashier API.
        Cashier::useSubscriptionModel(CashierSubscription::class);
        Cashier::useSubscriptionItemModel(CashierSubscriptionItem::class);

        // KeyHome ships its own webhook controller (`PaymentController::handleStripeWebhook`)
        // so the metadata-driven `Payment` lookup keeps working. Disable
        // Cashier's default webhook + UI routes to avoid double handling.
        // Cashier's migrations are *published* (taken over) rather than
        // ignored — the package detects published migrations and will not
        // re-load them, which is why we don't call `ignoreMigrations()`
        // (it doesn't exist in Cashier 16 anyway).
        Cashier::ignoreRoutes();

        // Prevent N+1 queries in dev/testing — throws exception on lazy loading
        Model::preventLazyLoading(!app()->isProduction());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Payment::observe(PaymentObserver::class);
        Ad::observe(AdObserver::class);
        TentativeReservation::observe(TentativeReservationObserver::class);
        Activity::observe(ActivityObserver::class);
        User::observe(UserObserver::class);
        PointPackage::observe(PointPackageObserver::class);
        SubscriptionPlan::observe(SubscriptionPlanObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Partage le logo et l'URL frontend avec toutes les vues emails.*
        // MAIL_ASSET_BASE_URL doit pointer vers le domaine public (ex: https://keyhome.app)
        // pour que les clients email (Gmail, Outlook) puissent charger l'image.
        // Les data: URI sont bloqués par la plupart des clients email modernes.
        View::composer(['emails.*', 'emails.reservation.*'], function ($view): void {
            $assetBase = rtrim((string) config('app.mail_asset_base_url', config('app.url')), '/');

            // Client (pink) logo
            $logoPath = public_path('images/keyhomelogo_email.png');
            $emailLogoUrl = $assetBase.'/images/keyhomelogo_email.png';
            $emailLogoBase64 = '';
            if (file_exists($logoPath) && filesize($logoPath) < 150000) {
                $emailLogoBase64 = base64_encode((string) file_get_contents($logoPath));
            }

            // Owner (teal) logo — used by owner-layout
            $tealLogoPath = public_path('images/logo-teal.png');
            $emailOwnerLogoUrl = $assetBase.'/images/logo-teal.png';
            $emailOwnerLogoBase64 = '';
            if (file_exists($tealLogoPath) && filesize($tealLogoPath) < 150000) {
                $emailOwnerLogoBase64 = base64_encode((string) file_get_contents($tealLogoPath));
            }

            $view->with([
                'emailLogoUrl' => $emailLogoUrl,
                'emailLogoBase64' => $emailLogoBase64,
                'emailOwnerLogoUrl' => $emailOwnerLogoUrl,
                'emailOwnerLogoBase64' => $emailOwnerLogoBase64,
                'emailFrontendUrl' => config('app.frontend_url', config('app.url')),
            ]);
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
            $authUser = $request->user();

            if (!$authUser instanceof User) {
                return Limit::perMinute(60)->by($request->ip());
            }

            $agency = $authUser->agency;

            return match ($authUser->role) {
                UserRole::ADMIN => Limit::none(),
                UserRole::AGENT => ($agency instanceof Agency && $agency->hasActiveSubscription())
                    ? Limit::perMinute(500)->by($authUser->id)
                    : Limit::perMinute(300)->by($authUser->id),
                default => Limit::perMinute(300)->by($authUser->id),
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

        RateLimiter::for('viewings.reserve', fn (Request $r) => Limit::perMinute(max(1, config('rate_limiting.viewings.reserve', 20)))
            ->by(optional($r->user())->id ?? $r->ip()));
    }

    private function resolvePaymentGateway(mixed $app, string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'flutterwave' => $app->make(FlutterwavePaymentService::class),
            'stripe' => $app->make(StripePaymentService::class),
            default => throw new \InvalidArgumentException("Payment gateway [{$name}] not supported."),
        };
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
