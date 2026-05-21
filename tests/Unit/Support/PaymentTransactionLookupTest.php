<?php

declare(strict_types=1);

use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentTransactionLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds payment by genius sandbox reference for owner', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'gateway_response' => ['genius_reference' => 'SANDBOX_V1A7ZXW9QSR8HHP6'],
    ]);

    Payment::factory()->pending()->create([
        'user_id' => $other->id,
        'gateway' => 'geniuspay',
        'gateway_response' => ['genius_reference' => 'SANDBOX_V1A7ZXW9QSR8HHP6'],
    ]);

    $found = PaymentTransactionLookup::findForUser(
        $user,
        null,
        'SANDBOX_V1A7ZXW9QSR8HHP6',
    );

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($payment->id);
});

it('finds payment by public genius reference', function (): void {
    $payment = Payment::factory()->pending()->create([
        'gateway' => 'geniuspay',
        'gateway_response' => ['genius_reference' => 'MTX-PUBLIC-LOOKUP'],
    ]);

    $found = PaymentTransactionLookup::findByPublicReference('MTX-PUBLIC-LOOKUP');

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($payment->id);
});

it('finds credit payment by reference with type filter', function (): void {
    $user = User::factory()->create();

    $credit = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'type' => PaymentType::CREDIT,
        'gateway' => 'geniuspay',
        'gateway_response' => ['genius_reference' => 'SANDBOX-CREDIT-1'],
    ]);

    Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'type' => PaymentType::UNLOCK,
        'gateway' => 'geniuspay',
        'gateway_response' => ['genius_reference' => 'SANDBOX-CREDIT-1'],
    ]);

    $found = PaymentTransactionLookup::findForUser(
        $user,
        null,
        'SANDBOX-CREDIT-1',
        PaymentType::CREDIT,
    );

    expect($found)->not->toBeNull()
        ->and($found?->id)->toBe($credit->id);
});
