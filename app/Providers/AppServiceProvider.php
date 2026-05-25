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
use App\Models\CashierSubscription;
use App\Models\CashierSubscriptionItem;
use App\Models\EmailSuppression;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Ai\AiSearchService;
use App\Services\Ai\RecommendationEngine;
use App\Services\Contracts\ReservationServiceInterface;
use App\Services\Contracts\ViewingScheduleServiceInterface;
use App\Services\Payment\FlutterwavePaymentService;
use App\Services\Payment\GeniusPayPaymentService;
use App\Services\Payment\PaymentMethodGateService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use App\Services\ReservationService;
use App\Services\TrustScoreService;
use App\Services\ViewingScheduleService;
use App\Services\WebAuthn\CacheChallengeRepository;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;
use Laravel\Cashier\Cashier;
use Laravel\Sanctum\Sanctum;

/**
 * Core application service provider.
 *
 * Responsibilities:
 * - Interface bindings and singleton registrations (register)
 * - Cashier model overrides
 * - Sanctum personal access token model
 * - Eloquent lazy-loading guard
 * - HTTPS URL forcing
 * - Email view composers (logo URLs, frontend URL)
 * - Password reset & email verification URL customisation
 * - Gate definitions
 *
 * Observer registrations → ObserverServiceProvider
 * Rate limiter definitions  → RateLimitServiceProvider
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register interface bindings, singletons, and service container overrides.
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
            $defaultName = (string) config('payment.default', 'geniuspay');
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
            // default gateway is GeniusPay.
            $stripe = $app->make(StripePaymentService::class);
            $registry[$stripe->getName()] = $stripe;

            // Legacy Flutterwave rows may still exist in the database.
            $flutterwave = $app->make(FlutterwavePaymentService::class);
            $registry[$flutterwave->getName()] = $flutterwave;

            return new PaymentService($gateway, $fallback, $registry);
        });
    }

    /**
     * Bootstrap application services: Cashier, Sanctum, lazy-loading guard,
     * URL forcing, email view composers, notification URL overrides, and Gates.
     *
     * Observers are registered in ObserverServiceProvider.
     * Rate limiters are registered in RateLimitServiceProvider.
     */
    public function boot(): void
    {
        $this->ensureLivewireTmpDirectoryExists();

        // ── Cashier model overrides ───────────────────────────────────────────
        // `subscriptions` was renamed to `cashier_subscriptions` in
        // `database/migrations/..._create_subscriptions_table.php` to avoid
        // collision with the existing business `subscriptions`
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

        // ── Model configuration ───────────────────────────────────────────────

        // Prevent N+1 queries in dev/testing — throws exception on lazy loading
        Model::preventLazyLoading(!app()->isProduction());

        // ── URL configuration ─────────────────────────────────────────────────

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // ── Sanctum ───────────────────────────────────────────────────────────

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // ── Email view composers ──────────────────────────────────────────────

        // Shares the hosted logo URLs with all emails.* views.
        // MAIL_ASSET_BASE_URL must point to the public domain (e.g. https://keyhome.app)
        // so Gmail, Outlook, and Apple Mail can load the image.
        // data: URI base64 embeds are blocked by virtually every modern email client
        // and have been removed — always use the hosted URL.
        $assetBase = rtrim((string) config('app.mail_asset_base_url', config('app.url')), '/');

        $emailViewData = [
            'emailLogoUrl' => $assetBase.'/images/keyhomelogo_email.png',
            'emailOwnerLogoUrl' => $assetBase.'/images/logo-teal.png',
            'emailFrontendUrl' => config('app.frontend_url', config('app.url')),
        ];

        View::composer(['emails.*', 'emails.reservation.*'], static function ($view) use ($emailViewData): void {
            $view->with($emailViewData);
        });

        // ── E-2 : Suppression guard ───────────────────────────────────────────
        // Return false to cancel sending to any address in email_suppressions.
        // Laravel's Mailer::sendNow() uses events->until(), so false stops the send.
        Event::listen(MessageSending::class, static function (MessageSending $event): ?bool {
            $recipients = array_keys(
                array_merge(
                    $event->message->getTo(),
                    $event->message->getCc(),
                    $event->message->getBcc(),
                )
            );

            foreach ($recipients as $address) {
                if (EmailSuppression::where('email', strtolower((string) $address))->exists()) {
                    Log::info('mail.suppressed', ['email' => $address]);

                    return false;
                }
            }

            return null;
        });

        // ── Auto plain-text MIME part ─────────────────────────────────────────
        // Resend (and most ESPs) flag emails that ship only text/html as a
        // deliverability risk. Rather than adding `text:` to every Mailable,
        // we generate a plain-text fallback from the HTML body at send time.
        Event::listen(MessageSending::class, static function (MessageSending $event): void {
            if ($event->message->getTextBody() !== null) {
                return;
            }

            $html = $event->message->getHtmlBody();

            if ($html === null || $html === '') {
                return;
            }

            // Preserve anchor href so links remain usable in plain-text clients.
            $plain = (string) preg_replace_callback(
                '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
                static function (array $m): string {
                    $label = trim(strip_tags($m[2]));
                    $url = trim($m[1]);

                    return $label !== '' && $url !== $label
                        ? "{$label} ( {$url} )"
                        : $label;
                },
                $html
            );
            $plain = (string) preg_replace(
                ['/<br\s*\/?>/i', '/<\/(?:p|div|li|h[1-6]|tr)>/i'],
                "\n",
                $plain
            );
            $plain = html_entity_decode(
                strip_tags($plain),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $plain = (string) preg_replace('/\n{3,}/', "\n\n", trim($plain));

            $event->message->text($plain);
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

        // ── Gates ─────────────────────────────────────────────────────────────

        Gate::define('viewPulse', fn (?User $user = null) => $user?->isAdmin() ?? false);
    }

    private function resolvePaymentGateway(mixed $app, string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'geniuspay' => $app->make(GeniusPayPaymentService::class),
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
