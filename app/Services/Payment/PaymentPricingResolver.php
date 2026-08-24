<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\PointPackage;
use App\Models\SubscriptionPlan;

/**
 * Resolves the authoritative, server-side price for a payment request.
 *
 * Extracted out of PaymentService: the orchestrator should never trust a
 * client-sent amount, so the controller asks this resolver for the price of
 * the chosen credit package or subscription plan before initiating a payment.
 */
final readonly class PaymentPricingResolver
{
    /**
     * Resolve the authoritative server-side price for a payment type.
     *
     * @param  array<string, mixed>  $validated
     */
    public function resolveAmountForType(string $type, array $validated): ?float
    {
        return match ($type) {
            'credit' => $this->resolveCreditAmount($validated['plan_id'] ?? null),
            'subscription' => $this->resolveSubscriptionAmount($validated['plan_id'] ?? null, $validated['period'] ?? 'monthly'),
            default => null,
        };
    }

    private function resolveCreditAmount(?string $packageId): ?float
    {
        if (!$packageId) {
            return null;
        }

        $package = PointPackage::where('id', $packageId)->where('is_active', true)->first();

        return $package ? (float) $package->price : null;
    }

    private function resolveSubscriptionAmount(?string $planId, string $period): ?float
    {
        if (!$planId) {
            return null;
        }

        $plan = SubscriptionPlan::where('id', $planId)->where('is_active', true)->first();

        if (!$plan) {
            return null;
        }

        return $period === 'yearly' && $plan->price_yearly
            ? (float) $plan->price_yearly
            : (float) $plan->price;
    }
}
