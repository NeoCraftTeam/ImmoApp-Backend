<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Guards against the webhook-vs-client races: a stale in-memory model must
 * never overwrite a payment already settled by a concurrent webhook, and a
 * payment must never activate two subscriptions.
 */
it('safe redirect hint never overwrites a payment settled concurrently', function (): void {
    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-RACE-HINT-1',
        'status' => PaymentStatus::PENDING,
        'type' => 'credit',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 5000,
    ]);

    // Load a stale instance (still PENDING in memory)…
    $stale = Payment::query()->findOrFail($payment->id);

    // …then a "concurrent webhook" settles the row in DB.
    Payment::query()->whereKey($payment->id)->update(['status' => PaymentStatus::SUCCESS]);

    $result = app(PaymentService::class)->applySafeRedirectTerminalHint($stale, 'cancelled');

    expect($result->status)->toBe(PaymentStatus::SUCCESS);
    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => PaymentStatus::SUCCESS->value,
    ]);
});

it('still applies the cancelled hint when the payment is genuinely pending', function (): void {
    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-RACE-HINT-2',
        'status' => PaymentStatus::PENDING,
        'type' => 'credit',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 5000,
    ]);

    $result = app(PaymentService::class)->applySafeRedirectTerminalHint($payment, 'cancelled');

    expect($result->status)->toBe(PaymentStatus::CANCELLED);
});

it('never promotes pending to success from a redirect hint', function (): void {
    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-RACE-HINT-3',
        'status' => PaymentStatus::PENDING,
        'type' => 'credit',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 5000,
    ]);

    $result = app(PaymentService::class)->applySafeRedirectTerminalHint($payment, 'completed');

    expect($result->status)->toBe(PaymentStatus::PENDING);
});

it('rejects a second subscription row for the same payment at the DB level', function (): void {
    $user = User::factory()->create();
    $agency = Agency::factory()->create();
    $plan = SubscriptionPlan::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'KH-RACE-SUB-1',
        'status' => PaymentStatus::SUCCESS,
        'type' => 'subscription',
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'amount' => 10000,
    ]);

    $base = [
        'agency_id' => $agency->id,
        'subscription_plan_id' => $plan->id,
        'payment_id' => $payment->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ];

    Subscription::query()->create($base);

    expect(fn () => Subscription::query()->create($base))
        ->toThrow(UniqueConstraintViolationException::class);
});
