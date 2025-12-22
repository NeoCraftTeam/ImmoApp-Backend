<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Vérifiez votre adresse email')
                ->view('emails.verify-email', ['url' => $url, 'user' => $notifiable]);
        });

        VerifyEmail::createUrlUsing(function ($notifiable) {
            $frontendUrl = config('app.email_verify_callback');

            $verifyUrl = URL::temporarySignedRoute(
                'api.verification.verify',
                Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => (string) $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            return $frontendUrl . '/verify-email?verify_url=' . urlencode($verifyUrl);
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $link = "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->getEmailForVerification()}";

            \Illuminate\Support\Facades\Log::error("PASSWORD RESET LINK: " . $link); // Force LOG

            return $link;
        });
    }
}
