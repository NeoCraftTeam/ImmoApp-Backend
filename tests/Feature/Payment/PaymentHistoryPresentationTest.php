<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->assertJsonPath('data.0.payment_method_detail', 'paiement@test.example')
        ->assertJsonPath('data.0.gateway_label', 'Stripe')
        ->assertJsonPath('data.0.payment_method', PaymentMethod::CARD->value);
});

it('masks phone detail when kh_payment_trace is absent for mobile wallets', function (): void {
    $user = User::factory()->create();

    Payment::factory()->success()->create([
        'user_id' => $user->id,
        'gateway' => 'geniuspay',
        'payment_method' => PaymentMethod::ORANGE_MONEY->value,
        'phone_number' => '+237 6 71 82 93 94',
        'gateway_response' => ['status' => 'ok'],
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/payments/history');

    $response->assertSuccessful();
    expect($response->json('data.0.payment_method_detail'))->toBe('··· 9394');
    expect($response->json('data.0.gateway_label'))->toBe('GeniusPay');
});
