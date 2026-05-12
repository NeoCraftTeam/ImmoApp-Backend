<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionExpiringEmail;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $daysToNotify = [7, 3, 1];

        foreach ($daysToNotify as $days) {

            $subscriptions = Subscription::where('status', SubscriptionStatus::ACTIVE)
                ->whereDate('ends_at', '=', now()->addDays($days)->toDateString())
                ->with('agency.users')
                ->get();

            foreach ($subscriptions as $subscription) {
                foreach ($subscription->agency->users as $user) {
                    try {
                        Mail::to($user->email)
                            ->send(new SubscriptionExpiringEmail($subscription, $days));
                    } catch (\Throwable $e) {
                        Log::error("Failed to send expiry email to {$user->email}: ".$e->getMessage());
                    }
                }
                $this->line("Rappel de {$days} jours envoyé pour l'agence: {$subscription->agency->name}");
            }
        }

        // Marquer comme expirés ceux qui sont passés
        $expiredCount = app(SubscriptionService::class)->expireSubscriptions();
        $this->info("{$expiredCount} abonnements marqués comme expirés.");

        $this->info('Vérification terminée.');
    }
}
