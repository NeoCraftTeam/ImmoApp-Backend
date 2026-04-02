<?php

declare(strict_types=1);

// app/Listeners/SendWelcomeNotification.php

namespace App\Listeners;

use App\Models\User;
use App\Services\UserWelcomeService;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendWelcomeNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Handle the event.
     */
    public function handle(Verified $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $cacheKey = 'welcome_email_sent_'.$user->id;
        if (Cache::has($cacheKey)) {
            Log::info('Welcome email already sent recently, skipping duplicate', ['user_id' => $user->id]);

            return;
        }
        Cache::put($cacheKey, true, now()->addMinutes(5));

        try {
            Log::info('User email verified - triggering welcome actions', [
                'user_id' => $user->id,
                'email' => $user->email,
                'verified_at' => $user->email_verified_at,
                'role' => $user->role->value,
            ]);

            app(UserWelcomeService::class)->handle($user);

            $this->updateUserStats($user);

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
     * Handle a job failure.
     */
    public function failed(Verified $event, Exception $exception): void
    {
        /** @var User $user */
        $user = $event->user;

        Log::error('SendWelcomeNotification job failed', [
            'user_id' => $user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
