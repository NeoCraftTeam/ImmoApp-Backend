<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\Ad::observe(\App\Observers\AdObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Force tous les liens de vérification (Web, Filament, API) à utiliser ma route publique sécurisée
        \Illuminate\Auth\Notifications\VerifyEmail::createUrlUsing(fn ($notifiable) => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ]
        ));

        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Vérifiez votre adresse email')
            ->view('emails.verify-email', ['url' => $url, 'user' => $notifiable]));

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $link = "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->getEmailForVerification()}";

            \Illuminate\Support\Facades\Log::error('PASSWORD RESET LINK: '.$link); // Force LOG

            return $link;
        });
    }
}
