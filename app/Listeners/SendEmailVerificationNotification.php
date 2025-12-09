<?php

// app/Listeners/SendEmailVerificationNotification.php

namespace App\Listeners;

use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendEmailVerificationNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;

        try {
            // Vérifier si l'utilisateur implémente MustVerifyEmail
            if (! method_exists($user, 'hasVerifiedEmail')) {
                Log::warning('User does not implement MustVerifyEmail', [
                    'user_id' => $user->id,
                ]);

                return;
            }

            // Envoyer l'email de vérification seulement si pas déjà vérifié
            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();

                Log::info('Email verification sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'sent_at' => now(),
                ]);
            } else {
                Log::info('User email already verified, skipping verification email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to send email verification', [
                'user_id' => $user->id,
                'email' => $user->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // En cas d'erreur, ne pas faire échouer toute l'inscription
            // L'utilisateur peut toujours demander un renvoi
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Registered $event, Exception $exception): void
    {
        Log::error('SendEmailVerificationNotification job failed', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
