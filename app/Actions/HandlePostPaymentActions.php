<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Mail\CreditPurchaseConfirmationMail;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PointService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles post-payment side effects (subscription activation or credit fulfillment).
 *
 * Extracted from PaymentController to eliminate duplication between verify(),
 * handleWebhook(), and CreditController::verifyPurchase().
 */
final readonly class HandlePostPaymentActions
{
    public function __construct(
        private PointService $pointService,
        private SubscriptionService $subscriptionService,
    ) {}

    /**
     * @param  array<string, mixed>  $webhookData  Raw gateway response data
     */
    public function execute(Payment $payment, array $webhookData = []): void
    {
        DB::transaction(function () use ($payment, $webhookData): void {
            $metadata = (array) ($webhookData['meta'] ?? []);

            match ($payment->type) {
                PaymentType::SUBSCRIPTION => $this->activateSubscription($payment, $metadata),
                PaymentType::CREDIT => $this->fulfillCreditPurchase($payment, $metadata),
                PaymentType::BOOST => $this->activateBoost($payment, $metadata),
                default => null,
            };
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function activateSubscription(Payment $payment, array $metadata): void
    {
        // Idempotency guard: a subscription already linked to this payment means
        // a previous job/webhook already handled it. Prevents duplicate subscriptions
        // when webhook retries or jobs are re-processed.
        if (Subscription::where('payment_id', $payment->id)->exists()) {
            Log::info('Abonnement déjà activé pour ce paiement, skip', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        $agencyId = $payment->agency_id ?? ($metadata['agency_id'] ?? null);
        $planId = $payment->plan_id ?? ($metadata['plan_id'] ?? null);
        $period = $payment->period ?? ($metadata['period'] ?? 'monthly');

        if (!$agencyId || !$planId) {
            return;
        }

        $agency = Agency::find($agencyId);
        $plan = SubscriptionPlan::find($planId);

        if (!$agency || !$plan) {
            return;
        }

        $subscription = $this->subscriptionService->createSubscription($agency, $plan, $period, $payment);
        $this->subscriptionService->activateSubscription($subscription);

        Log::info("Abonnement activé: agence {$agency->id} - plan {$plan->id}");
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function fulfillCreditPurchase(Payment $payment, array $metadata): void
    {
        $package = $this->resolvePointPackage($payment, $metadata);
        // Re-fetch buyer with lock to prevent double-crediting from concurrent webhooks
        $buyer = User::lockForUpdate()->find($payment->user_id);

        if (!$package || !$buyer) {
            return;
        }

        $alreadyCredited = $buyer->pointTransactions()
            ->where('payment_id', $payment->id)
            ->exists();

        if ($alreadyCredited) {
            return;
        }

        $this->pointService->credit(
            $buyer,
            $package->points_awarded,
            PointTransactionType::PURCHASE,
            "Achat pack: {$package->name}",
            $payment->id,
        );

        Log::info("Points crédités: {$package->points_awarded} → user {$buyer->id}");

        try {
            Mail::to($buyer->email)->send(new CreditPurchaseConfirmationMail(
                $buyer,
                $package,
                $payment,
                (int) $buyer->fresh()->point_balance,
            ));
        } catch (\Exception $e) {
            Log::error('Erreur email achat crédits: '.$e->getMessage());
        }
    }

    /**
     * Activate boost on the ad after a successful BOOST payment.
     * Idempotent: skip if the ad is already boosted by this payment.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function activateBoost(Payment $payment, array $metadata): void
    {
        $adId = $payment->ad_id ?? ($metadata['ad_id'] ?? null);
        $planId = $payment->plan_id ?? ($metadata['plan_id'] ?? null);

        if (!$adId) {
            Log::warning('Boost payment sans ad_id — impossible d\'activer le boost', ['payment_id' => $payment->id]);

            return;
        }

        $ad = Ad::find($adId);

        if (!$ad) {
            Log::warning('Boost payment: annonce introuvable', ['payment_id' => $payment->id, 'ad_id' => $adId]);

            return;
        }

        // Idempotency: if the ad was already boosted by this exact payment, skip.
        if ($ad->boosted_at && $ad->isBoosted()) {
            Log::info('Boost déjà actif pour cette annonce, skip', ['payment_id' => $payment->id, 'ad_id' => $adId]);

            return;
        }

        $plan = $planId ? SubscriptionPlan::find($planId) : null;
        $boostScore = $plan !== null ? ($plan->boost_score ?? 100) : 100;
        $durationDays = $plan !== null ? ($plan->boost_duration_days ?? 7) : 7;

        $ad->boost($boostScore, $durationDays);

        Log::info('Boost activé via paiement', [
            'payment_id' => $payment->id,
            'ad_id' => $adId,
            'boost_score' => $boostScore,
            'duration_days' => $durationDays,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolvePointPackage(Payment $payment, array $metadata): ?PointPackage
    {
        $packageId = $payment->plan_id ?? ($metadata['package_id'] ?? null);
        $package = $packageId ? PointPackage::find($packageId) : null;

        if (!$package) {
            $package = PointPackage::where('price', $payment->amount)
                ->where('is_active', true)
                ->first();
        }

        return $package;
    }
}
