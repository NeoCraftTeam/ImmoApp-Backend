<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monetization\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptionRenewals extends Command
{
    protected $signature = 'app:process-subscription-renewals';

    protected $description = 'Envoyer des rappels de renouvellement aux abonnements auto-renew expirant bientôt';

    public function handle(SubscriptionService $subscriptionService): void
    {
        $this->info('Traitement des renouvellements d\'abonnement...');

        $count = $subscriptionService->processRenewals();

        $this->info("{$count} rappel(s) de renouvellement envoyé(s).");
    }
}
