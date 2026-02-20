<?php

declare(strict_types=1);

namespace App\Providers;

// use App\Listeners\SendEmailVerificationNotification;
use App\Listeners\SendWelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string|string>>
     */
    protected $listen = [
        // Événement déclenché après l'inscription
        // Registered::class => [
        //     SendEmailVerificationNotification::class,
        // ],

        // Événement déclenché après vérification email
        Verified::class => [
            SendWelcomeNotification::class,
        ],

        // Socialite Apple Provider
        SocialiteWasCalled::class => [
            AppleExtendSocialite::class.'@handle',
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
