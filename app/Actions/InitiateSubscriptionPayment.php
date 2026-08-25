<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PaymentType;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payment\PaymentService;

/**
 * Creates the gateway payment for an agency subscription flow — subscribe,
 * renew, or upgrade. Centralises the createPayment payload the three
 * controller entry points used to duplicate: the SUBSCRIPTION type, the
 * Orange Money method, and the metadata the post-payment webhook reads back
 * (`action` + `subscription_id`) to fulfil the right operation.
 *
 * No state mutation happens here. Subscription::renew()/upgradeTo() run from
 * HandlePostPaymentActions only after a signed gateway webhook confirms the
 * payment, so an unpaid initiate can never grant paid service.
 */
final readonly class InitiateSubscriptionPayment
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * @return array{payment: Payment, link: string, tx_ref: string, gateway: string, status: string, stripe_flow: string|null}
     */
    public function execute(
        User $user,
        Agency $agency,
        SubscriptionPlan $plan,
        string $period,
        int $amount,
        string $description,
        ?string $action = null,
        ?Subscription $subscription = null,
    ): array {
        $meta = ['payment_type' => 'subscription'];

        if ($action !== null) {
            $meta['action'] = $action;
        }

        $meta['agency_id'] = $agency->id;
        $meta['plan_id'] = $plan->id;

        if ($subscription !== null) {
            $meta['subscription_id'] = $subscription->id;
        }

        $meta['period'] = $period;

        return $this->paymentService->createPayment($user, [
            'amount' => (float) $amount,
            'type' => PaymentType::SUBSCRIPTION->value,
            'payment_method' => 'orange_money',
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'period' => $period,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
