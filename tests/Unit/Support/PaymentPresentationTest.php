<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\PaymentPresentation;

it('shows mobile hierarchy for kpay orange money', function (): void {
    $payment = Payment::factory()->success()->make([
        'gateway' => 'kpay',
        'payment_method' => PaymentMethod::ORANGE_MONEY->value,
        'gateway_response' => [
            'kh_payment_trace' => [
                'label_fr' => 'Mobile',
                'detail_fr' => 'Orange Money',
                'kpay_provider' => 'orange_cm',
                'instrument_family' => 'mobile_money',
            ],
        ],
    ]);

    $row = PaymentPresentation::forPayment($payment);

    expect($row['payment_method_label'])->toBe('Mobile');
    expect($row['payment_method_detail'])->toBe('Orange Money');
});

it('shows card hierarchy with last four digits from stripe trace', function (): void {
    $payment = Payment::factory()->success()->make([
        'gateway' => 'stripe',
        'payment_method' => PaymentMethod::CARD->value,
        'gateway_response' => [
            'kh_payment_trace' => [
                'label_fr' => 'Carte bancaire',
                'detail_fr' => 'Visa · •••• 4242',
                'stripe_payment_method_type' => 'card',
            ],
        ],
    ]);

    $row = PaymentPresentation::forPayment($payment);

    expect($row['payment_method_label'])->toBe('Carte');
    expect($row['payment_method_detail'])->toBe('•••• 4242');
});

it('shows paypal as primary wallet label', function (): void {
    $payment = Payment::factory()->success()->make([
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

    $row = PaymentPresentation::forPayment($payment);

    expect($row['payment_method_label'])->toBe('PayPal');
    expect($row['payment_method_detail'])->toBeNull();
});

it('shows apple pay with masked card detail', function (): void {
    $payment = Payment::factory()->success()->make([
        'gateway' => 'stripe',
        'payment_method' => PaymentMethod::CARD->value,
        'gateway_response' => [
            'kh_payment_trace' => [
                'label_fr' => 'Apple Pay',
                'detail_fr' => 'Visa · •••• 1881',
                'stripe_payment_method_type' => 'apple_pay',
            ],
        ],
    ]);

    $row = PaymentPresentation::forPayment($payment);

    expect($row['payment_method_label'])->toBe('Apple Pay');
    expect($row['payment_method_detail'])->toBe('•••• 1881');
});

it('shows google pay with masked card detail', function (): void {
    $payment = Payment::factory()->success()->make([
        'gateway' => 'stripe',
        'payment_method' => PaymentMethod::CARD->value,
        'gateway_response' => [
            'kh_payment_trace' => [
                'label_fr' => 'Google Pay',
                'detail_fr' => 'Mastercard · •••• 4444',
                'stripe_payment_method_type' => 'google_pay',
            ],
        ],
    ]);

    $row = PaymentPresentation::forPayment($payment);

    expect($row['payment_method_label'])->toBe('Google Pay');
    expect($row['payment_method_detail'])->toBe('•••• 4444');
});

it('falls back to google pay label when only the enum is set', function (): void {
    $payment = Payment::factory()->success()->make([
        'gateway' => 'stripe',
        'payment_method' => PaymentMethod::GooglePay->value,
        'gateway_response' => null,
    ]);

    $row = PaymentPresentation::forPayment($payment);

    expect($row['payment_method_label'])->toBe('Google Pay');
});
