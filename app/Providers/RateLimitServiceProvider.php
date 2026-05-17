<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Configures all named rate limiters for the application.
 *
 * Extracted from AppServiceProvider to keep it focused on service bindings.
 * Limiter thresholds are configurable via config/rate_limiting.php.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap all named rate limiters.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Register all named rate limiters used by route middleware.
     */
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

        // ── Auth limiters (values configurable via config/rate_limiting.php) ──

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

        // ── Payment limiters ─────────────────────────────────────────────────

        RateLimiter::for('payments.initiate', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.initiate', 5))->by(optional($r->user())->id ?? $r->ip()));

        RateLimiter::for('payments.verify', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.verify', 30))->by(optional($r->user())->id ?? $r->ip()));

        RateLimiter::for('payments.cancel', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.cancel', 10))->by(optional($r->user())->id ?? $r->ip()));

        RateLimiter::for('payments.webhook', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.webhook', 120))->by($r->ip()));

        RateLimiter::for('payments.history', fn (Request $r) => Limit::perMinute(config('rate_limiting.payments.history', 60))->by(optional($r->user())->id ?? $r->ip()));

        // ── Viewing limiters ─────────────────────────────────────────────────

        RateLimiter::for('viewings.reserve', fn (Request $r) => Limit::perMinute(max(1, config('rate_limiting.viewings.reserve', 20)))
            ->by(optional($r->user())->id ?? $r->ip()));
    }
}
