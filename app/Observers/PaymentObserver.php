<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        $this->clearRecommendationCache($payment);
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        $this->clearRecommendationCache($payment);
    }

    /**
     * Clear the recommendation cache for the user.
     */
    protected function clearRecommendationCache(Payment $payment): void
    {
        // On invalide le cache seulement si le paiement est un succès (car cela change l'historique significatif)
        if ($payment->status === PaymentStatus::SUCCESS) {
            Cache::forget("recommendations_user_{$payment->user_id}");
        }
    }
}
