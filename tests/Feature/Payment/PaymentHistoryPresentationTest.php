<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('exposes kh_payment_trace labels on payment history', function (): void {
    $user = User::factory()->create();

    Payment::factory()->success()->create([
        'user_id' => $user->id,
        'gateway' => 'stripe',
        'payment_method' => PaymentMethod::CARD->value,
        'gateway_response' => [
            'kh_payment_trace' => [
                'label_fr' => 'PayPal',
                'detail_fr' => 'paiement@test.example',
                'stripe_payment_method_type' => 'paypal',
            ],
        ],
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/payments/history')
        ->assertSuccessful()
        ->assertJsonPath('data.0.payment_method_label', 'PayPal')
        ->assertJsonPath('data.0.payment_method_detail', null)
        ->assertJsonPath('data.0.gateway_label', 'Stripe')
        ->assertJsonPath('data.0.payment_method', PaymentMethod::CARD->value);
});

it('masks phone detail when kh_payment_trace is absent for mobile wallets', function (): void {
    $user = User::factory()->create();

    Payment::factory()->success()->create([
        'user_id' => $user->id,
        'gateway' => 'kpay',
        'payment_method' => PaymentMethod::ORANGE_MONEY->value,
        'phone_number' => '+237 6 71 82 93 94',
        'gateway_response' => ['status' => 'ok'],
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/payments/history');

    $response->assertSuccessful();
    expect($response->json('data.0.payment_method_label'))->toBe('Mobile');
    expect($response->json('data.0.payment_method_detail'))->toBe('Orange Money');
    expect($response->json('data.0.gateway_label'))->toBe('Kpay');
});

it('marks pending payment as failed when verify receives a safe redirect hint', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_hint_fail',
            'status' => 'PENDING',
            'amount' => 5000,
            'currency' => 'XAF',
        ], 200),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'type' => PaymentType::CREDIT,
        'gateway' => 'kpay',
        'amount' => 5000,
        'gateway_response' => ['kpay_id' => 'pay_hint_fail'],
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/credits/verify-purchase', [
        'tx_ref' => $payment->transaction_id,
        'gateway_redirect_status' => 'failed',
    ])
        ->assertStatus(422)
        ->assertJsonPath('status', 'failed');

    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED);
});
