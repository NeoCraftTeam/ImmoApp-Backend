<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Mail\CreditPurchaseConfirmationMail;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\PointPackage;
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
                default => null,
            };
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function activateSubscription(Payment $payment, array $metadata): void
    {
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
        $buyer = User::find($payment->user_id);

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
