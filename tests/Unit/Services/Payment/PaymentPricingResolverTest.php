<?php

declare(strict_types=1);

use App\Models\PointPackage;
use App\Models\SubscriptionPlan;
use App\Services\Payment\PaymentPricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Locks the authoritative server-side pricing extracted out of
 * {@see PaymentService} into {@see PaymentPricingResolver}. The controller
 * never trusts a client-sent amount, so these branches guard against a
 * regression that would let a caller pay the wrong price.
 */
function resolver(): PaymentPricingResolver
{
    return app(PaymentPricingResolver::class);
}

// ─── credit ────────────────────────────────────────────────────────────────

it('resolves the price of an active credit package', function (): void {
    $package = PointPackage::factory()->create(['price' => 500, 'is_active' => true]);

    expect(resolver()->resolveAmountForType('credit', ['plan_id' => $package->id]))->toBe(500.0);
});

it('returns null for an inactive credit package', function (): void {
    $package = PointPackage::factory()->inactive()->create(['price' => 500]);

    expect(resolver()->resolveAmountForType('credit', ['plan_id' => $package->id]))->toBeNull();
});

it('returns null when a credit request carries no plan_id', function (): void {
    expect(resolver()->resolveAmountForType('credit', []))->toBeNull();
});

// ─── subscription ────────────────────────────────────────────────────────

it('resolves the monthly price of an active subscription plan', function (): void {
    $plan = SubscriptionPlan::factory()->create([
        'price' => 15000,
        'price_yearly' => 150000,
        'is_active' => true,
    ]);

    expect(resolver()->resolveAmountForType('subscription', ['plan_id' => $plan->id, 'period' => 'monthly']))->toBe(15000.0);
});

it('resolves the yearly price when price_yearly is set', function (): void {
    $plan = SubscriptionPlan::factory()->create([
        'price' => 15000,
        'price_yearly' => 150000,
        'is_active' => true,
    ]);

    expect(resolver()->resolveAmountForType('subscription', ['plan_id' => $plan->id, 'period' => 'yearly']))->toBe(150000.0);
});

it('falls back to the monthly price for a yearly period when price_yearly is null', function (): void {
    $plan = SubscriptionPlan::factory()->create([
        'price' => 15000,
        'price_yearly' => null,
        'is_active' => true,
    ]);

    expect(resolver()->resolveAmountForType('subscription', ['plan_id' => $plan->id, 'period' => 'yearly']))->toBe(15000.0);
});

it('defaults the period to monthly when it is absent', function (): void {
    $plan = SubscriptionPlan::factory()->create([
        'price' => 15000,
        'price_yearly' => 150000,
        'is_active' => true,
    ]);

    expect(resolver()->resolveAmountForType('subscription', ['plan_id' => $plan->id]))->toBe(15000.0);
});

it('returns null for an inactive subscription plan', function (): void {
    $plan = SubscriptionPlan::factory()->create(['is_active' => false]);

    expect(resolver()->resolveAmountForType('subscription', ['plan_id' => $plan->id, 'period' => 'monthly']))->toBeNull();
});

it('returns null when a subscription request carries no plan_id', function (): void {
    expect(resolver()->resolveAmountForType('subscription', []))->toBeNull();
});

// ─── other types ───────────────────────────────────────────────────────────

it('returns null for a payment type that has no server-resolved price', function (string $type): void {
    expect(resolver()->resolveAmountForType($type, ['plan_id' => 'anything']))->toBeNull();
})->with(['unlock', 'boost', 'mystery']);
