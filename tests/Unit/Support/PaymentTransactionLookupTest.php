<?php

declare(strict_types=1);

use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentTransactionLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds payment by kpay id for owner', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'gateway_response' => ['kpay_id' => 'pay_lookup_owner'],
    ]);

    Payment::factory()->pending()->create([
        'user_id' => $other->id,
        'gateway' => 'kpay',
        'gateway_response' => ['kpay_id' => 'pay_lookup_owner'],
    ]);

    $found = PaymentTransactionLookup::findForUser(
        $user,
        null,
        'pay_lookup_owner',
    );

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($payment->id);
});

it('finds payment by public kpay reference', function (): void {
    $payment = Payment::factory()->pending()->create([
        'gateway' => 'kpay',
        'gateway_response' => ['reference' => 'KPAY-PUBLIC-LOOKUP'],
    ]);

    $found = PaymentTransactionLookup::findByPublicReference('KPAY-PUBLIC-LOOKUP');

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($payment->id);
});

it('finds payment by kpay sandbox redirect reference', function (): void {
    $payment = Payment::factory()->pending()->create([
        'gateway' => 'kpay',
        'gateway_response' => ['reference' => 'SANDBOX_V1A7ZXW9QSR8HHP6'],
    ]);

    $found = PaymentTransactionLookup::findByPublicReference('SANDBOX_V1A7ZXW9QSR8HHP6');

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($payment->id);
});

it('recognizes sandbox references for gateway lookup but not as kpay api ids', function (): void {
    expect(PaymentTransactionLookup::isGatewayReference('SANDBOX_ABC123'))->toBeTrue()
        ->and(PaymentTransactionLookup::isKpayApiPaymentId('SANDBOX_ABC123'))->toBeFalse()
        ->and(PaymentTransactionLookup::isKpayApiPaymentId('pay_abc123'))->toBeTrue();
});

it('finds credit payment by reference with type filter', function (): void {
    $user = User::factory()->create();

    $credit = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'type' => PaymentType::CREDIT,
        'gateway' => 'kpay',
        'gateway_response' => ['kpay_id' => 'pay_credit_1'],
    ]);

    Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'type' => PaymentType::UNLOCK,
        'gateway' => 'kpay',
        'gateway_response' => ['kpay_id' => 'pay_credit_1'],
    ]);

    $found = PaymentTransactionLookup::findForUser(
        $user,
        null,
        'pay_credit_1',
        PaymentType::CREDIT,
    );

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($credit->id);
});
