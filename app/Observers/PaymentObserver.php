<?php

declare(strict_types=1);

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
            Cache::forget("reco_v2_user_{$payment->user_id}");
            $this->invalidateTrustScore($payment);
        }
    }

    protected function invalidateTrustScore(Payment $payment): void
    {
        $user = $payment->user;
        if ($user->trust_score_consent) {
            $context = $user->role->value === 'agent' ? 'landlord' : 'tenant';
            Cache::forget("trust_score:{$user->id}:{$context}");
        }
    }
}
