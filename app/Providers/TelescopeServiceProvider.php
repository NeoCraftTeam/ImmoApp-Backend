<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(fn (IncomingEntry $entry) => $isLocal ||
            $entry->isReportableException() ||
            $entry->isFailedRequest() ||
            $entry->isFailedJob() ||
            $entry->isScheduledTask() ||
            $entry->hasMonitoredTag());
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'secret_key',
            'token',
            'api_key',
        ]);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     *
     * Allowed principals are sourced from the `TELESCOPE_ALLOWED_EMAILS`
     * environment variable (comma-separated list) so rotating the admins
     * who can see production Telescope data does not require a code deploy
     * and never leaves an identifier committed in the repository. An admin
     * role on the User model is required in addition to the e-mail match —
     * defense in depth if the env var is misconfigured to include a
     * non-admin e-mail.
     *
     * Usage — `.env.production`:
     *     TELESCOPE_ALLOWED_EMAILS=ops@example.com,cto@example.com
     */
    #[\Override]
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user): bool {
            if (!method_exists($user, 'isAdmin') || !$user->isAdmin()) {
                return false;
            }

            if (!isset($user->email)) {
                return false;
            }

            $allowed = array_filter(array_map(
                trim(...),
                explode(',', (string) config('telescope.allowed_emails', ''))
            ));

            if ($allowed === []) {
                return false;
            }

            return in_array(strtolower((string) $user->email), array_map(strtolower(...), $allowed), true);
        });
    }
}
