<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression: ISSUE-001 — Stripe wallet payments (Apple Pay / Google Pay) threw
 * SQLSTATE 23514 on a *successful* payment, because `normaliseIntent()` started
 * returning `apple_pay` / `google_pay` while the `payments_payment_method_check`
 * constraint still only allowed orange_money / mobile_money / card / stripe /
 * flutterwave.
 *
 * Found by /qa on 2026-08-09.
 * Report: .gstack/qa-reports/qa-report-keyhome-app-2026-08-09.md
 */
it('persists a wallet payment method resolved from a Stripe intent', function (string $rawMethod, PaymentMethod $expected): void {
    $payment = Payment::factory()->pending()->stripe()->create();

    // Mirrors PaymentService::verify() / handleWebhook(): the gateway-resolved
    // string is mapped through the enum, then force-filled onto the payment.
    $resolved = PaymentMethod::tryFrom($rawMethod);

    expect($resolved)->toBe($expected);

    $payment->forceFill([
        'status' => PaymentStatus::SUCCESS,
        'payment_method' => $resolved,
    ])->save();

    $payment->refresh();

    expect($payment->payment_method)->toBe($expected)
        ->and($payment->status)->toBe(PaymentStatus::SUCCESS);
})->with([
    'apple pay' => ['apple_pay', PaymentMethod::ApplePay],
    'google pay' => ['google_pay', PaymentMethod::GooglePay],
]);

it('still rejects a payment method outside the allowed set', function (): void {
    $payment = Payment::factory()->pending()->stripe()->create();

    // Bypasses the enum cast on purpose: proves the CHECK constraint was
    // widened to the two wallet values, not dropped altogether.
    expect(fn (): int => Payment::query()
        ->whereKey($payment->id)
        ->update(['payment_method' => 'bogus_method']))
        ->toThrow(QueryException::class);
});
