<?php

// app/Listeners/SendWelcomeNotification.php

namespace App\Listeners;

use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendWelcomeNotification implements ShouldQueue
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
    public function handle(Verified $event): void
    {
        /** @var \App\Models\User $user */
        $user = $event->user;

        try {
            // 1. Logger la vérification
            Log::info('User email verified - triggering welcome actions', [
                'user_id' => $user->id,
                'email' => $user->email,
                'verified_at' => $user->email_verified_at,
                'role' => $user->role ?? 'unknown',
            ]);

            // 2. Envoyer email de bienvenue (optionnel)
            // Décommente si tu créés une classe WelcomeEmail
            // 2. Envoyer email de bienvenue
            if (class_exists(\App\Mail\WelcomeEmail::class)) {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\WelcomeEmail($user));
                Log::info('Welcome email sent', ['user_id' => $user->id]);
            }

            // 3. Autres actions automatiques que tu peux ajouter :

            // Mettre à jour des statistiques
            $this->updateUserStats($user);

            // Actions spécifiques selon le rôle
            $this->handleRoleSpecificActions($user);

            // 4. Logger le succès
            Log::info('Welcome notification process completed', [
                'user_id' => $user->id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to process welcome notification', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Mettre à jour les statistiques utilisateur
     */
    private function updateUserStats($user): void
    {
        try {
            // Exemple : compter les nouveaux utilisateurs vérifiés aujourd'hui
            // Tu peux créer une table stats ou utiliser cache

            Log::info('User stats updated', [
                'user_id' => $user->id,
                'verification_date' => now()->format('Y-m-d'),
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to update user stats', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Actions spécifiques selon le rôle
     */
    private function handleRoleSpecificActions($user): void
    {
        try {
            match ($user->role) {
                // Actions pour les clients
                'customer' => Log::info('Customer welcome actions triggered', [
                    'user_id' => $user->id,
                ]),
                // Actions pour les agents
                'agent' => Log::info('Agent welcome actions triggered', [
                    'user_id' => $user->id,
                ]),
                // Actions pour les admins
                'admin' => Log::info('Admin welcome actions triggered', [
                    'user_id' => $user->id,
                ]),
                default => Log::info('Default welcome actions triggered', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                ]),
            };

        } catch (Exception $e) {
            Log::warning('Failed to handle role-specific actions', [
                'user_id' => $user->id,
                'role' => $user->role,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Verified $event, Exception $exception): void
    {
        /** @var \App\Models\User $user */
        $user = $event->user;

        Log::error('SendWelcomeNotification job failed', [
            'user_id' => $user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
