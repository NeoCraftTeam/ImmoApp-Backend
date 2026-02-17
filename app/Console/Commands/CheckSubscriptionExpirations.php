<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckSubscriptionExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-subscription-expirations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifier les abonnements expirants et envoyer des rappels par email';

    public function handle(): void
    {
        $this->info('Vérification des expirations d\'abonnement...');

        $daysToNotify = [3, 1];

        foreach ($daysToNotify as $days) {

            $subscriptions = \App\Models\Subscription::where('status', \App\Enums\SubscriptionStatus::ACTIVE)
                ->whereDate('ends_at', '=', now()->addDays($days)->toDateString())
                ->with('agency.users')
                ->get();

            foreach ($subscriptions as $subscription) {
                foreach ($subscription->agency->users as $user) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($user->email)
                            ->send(new \App\Mail\SubscriptionExpiringEmail($subscription, $days));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to send expiry email to {$user->email}: ".$e->getMessage());
                    }
                }
                $this->line("Rappel de {$days} jours envoyé pour l'agence: {$subscription->agency->name}");
            }
        }

        // Marquer comme expirés ceux qui sont passés
        $expiredCount = app(\App\Services\SubscriptionService::class)->expireSubscriptions();
        $this->info("{$expiredCount} abonnements marqués comme expirés.");

        $this->info('Vérification terminée.');
    }
}
