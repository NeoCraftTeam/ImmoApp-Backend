<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AdCreated;
use App\Events\AdStatusTransitioned;
use App\Listeners\AutoBoostNewAd;
use App\Listeners\LogAuthenticationEvents;
use App\Listeners\MatchSearchAlertsOnAdAvailable;
use App\Listeners\NotifyAdminsOfPendingAd;
use App\Listeners\NotifyOwnerOfStatusChange;
use App\Listeners\SendBackupByEmailListener;
use App\Listeners\SendWelcomeNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\Backup\Events\BackupZipWasCreated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        // Événement déclenché après vérification email
        Verified::class => [
            SendWelcomeNotification::class,
        ],

        // Socialite Apple Provider
        SocialiteWasCalled::class => [
            AppleExtendSocialite::class.'@handle',
        ],

        // Backup: send zip by email when enabled
        BackupZipWasCreated::class => [
            SendBackupByEmailListener::class,
        ],

        // Ad lifecycle events
        AdCreated::class => [
            AutoBoostNewAd::class,
            NotifyAdminsOfPendingAd::class,
            MatchSearchAlertsOnAdAvailable::class,
        ],

        AdStatusTransitioned::class => [
            NotifyOwnerOfStatusChange::class,
            NotifyAdminsOfPendingAd::class,
            MatchSearchAlertsOnAdAvailable::class,
        ],

        // Security audit trail — log all auth events
        Login::class => [
            LogAuthenticationEvents::class.'@handleLogin',
        ],

        Logout::class => [
            LogAuthenticationEvents::class.'@handleLogout',
        ],

        Failed::class => [
            LogAuthenticationEvents::class.'@handleFailed',
        ],

        Lockout::class => [
            LogAuthenticationEvents::class.'@handleLockout',
        ],

        PasswordReset::class => [
            LogAuthenticationEvents::class.'@handlePasswordReset',
        ],
    ];

    /**
     * Register any events for your application.
     */
    #[\Override]
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    #[\Override]
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
