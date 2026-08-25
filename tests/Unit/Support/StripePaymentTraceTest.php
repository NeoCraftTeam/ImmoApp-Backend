<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Support\StripePaymentTrace;
use Stripe\Charge;
use Stripe\PaymentIntent;

/**
 * Locks the French-facing payment-method labelling extracted from
 * StripePaymentService into {@see StripePaymentTrace}. These branches were
 * previously unreachable by any test (they only ran inside the live webhook
 * path), so this suite pins the contract before the code can drift.
 */

/**
 * @param  array<string, mixed>  $paymentMethodDetails
 */
function traceCharge(array $paymentMethodDetails): Charge
{
    return Charge::constructFrom(['payment_method_details' => $paymentMethodDetails]);
}

/**
 * @param  array<int, string>  $paymentMethodTypes
 */
function traceIntent(array $paymentMethodTypes = []): PaymentIntent
{
    return PaymentIntent::constructFrom(['payment_method_types' => $paymentMethodTypes]);
}

it('labels a plain card with its brand and last four', function (): void {
    $charge = traceCharge([
        'type' => 'card',
        'card' => ['brand' => 'visa', 'last4' => '4242', 'wallet' => null],
    ]);

    expect(StripePaymentTrace::build(traceIntent(['card']), $charge))->toBe([
        'label_fr' => PaymentMethod::CARD->label(),
        'detail_fr' => 'Visa · •••• 4242',
        'stripe_payment_method_type' => 'card',
    ]);
});

it('maps card brands to their French label in the detail line', function (string $brand, string $expectedBrand): void {
    $charge = traceCharge([
        'type' => 'card',
        'card' => ['brand' => $brand, 'last4' => '1234', 'wallet' => null],
    ]);

    $trace = StripePaymentTrace::build(traceIntent(['card']), $charge);

    expect($trace['detail_fr'])->toBe("{$expectedBrand} · •••• 1234");
})->with([
    'visa' => ['visa', 'Visa'],
    'mastercard' => ['mastercard', 'Mastercard'],
    'amex' => ['amex', 'American Express'],
    'diners' => ['diners', 'Diners Club'],
]);

it('surfaces the wallet brand for tokenised cards', function (string $walletType, string $expectedLabel): void {
    $charge = traceCharge([
        'type' => 'card',
        'card' => [
            'brand' => 'visa',
            'last4' => '4242',
            'wallet' => ['type' => $walletType],
        ],
    ]);

    $trace = StripePaymentTrace::build(traceIntent(['card']), $charge);

    expect($trace)->toBe([
        'label_fr' => $expectedLabel,
        'detail_fr' => 'Visa · •••• 4242',
        'stripe_payment_method_type' => 'card',
    ]);
})->with([
    'apple_pay' => ['apple_pay', 'Apple Pay'],
    'google_pay' => ['google_pay', 'Google Pay'],
    'link' => ['link', 'Stripe Link'],
    'samsung_pay' => ['samsung_pay', 'Samsung Pay'],
]);

it('labels non-card instruments from the charge payment_method_details', function (string $pmType, string $expectedLabel): void {
    $charge = traceCharge(['type' => $pmType]);

    $trace = StripePaymentTrace::build(traceIntent([$pmType]), $charge);

    expect($trace['label_fr'])->toBe($expectedLabel)
        ->and($trace['detail_fr'])->toBeNull()
        ->and($trace['stripe_payment_method_type'])->toBe($pmType);
})->with([
    'paypal' => ['paypal', 'PayPal'],
    'link' => ['link', 'Stripe Link'],
    'klarna' => ['klarna', 'Klarna'],
    'sepa_debit' => ['sepa_debit', 'Prélèvement SEPA'],
    'ideal' => ['ideal', 'iDEAL'],
    'bancontact' => ['bancontact', 'Bancontact'],
    'wechat_pay' => ['wechat_pay', 'WeChat Pay'],
]);

it('falls back to a generic online-payment label for unknown instrument types', function (): void {
    $charge = traceCharge(['type' => 'oxxo']);

    $trace = StripePaymentTrace::build(traceIntent(['oxxo']), $charge);

    expect($trace)->toBe([
        'label_fr' => 'Paiement en ligne oxxo',
        'detail_fr' => null,
        'stripe_payment_method_type' => 'oxxo',
    ]);
});

it('detects PayPal from payment_method_types when no charge is present', function (): void {
    expect(StripePaymentTrace::build(traceIntent(['paypal']), null))->toBe([
        'label_fr' => 'PayPal',
        'detail_fr' => null,
        'stripe_payment_method_type' => 'paypal',
    ]);
});

it('detects Stripe Link from payment_method_types when no charge is present', function (): void {
    expect(StripePaymentTrace::build(traceIntent(['link']), null))->toBe([
        'label_fr' => 'Stripe Link',
        'detail_fr' => null,
        'stripe_payment_method_type' => 'link',
    ]);
});

it('falls back to the card label when the charge is missing', function (): void {
    expect(StripePaymentTrace::build(traceIntent(['card']), null))->toBe([
        'label_fr' => PaymentMethod::CARD->label(),
        'detail_fr' => null,
        'stripe_payment_method_type' => 'card',
    ]);
});

it('defaults the instrument type to card when nothing identifies the method', function (): void {
    expect(StripePaymentTrace::build(traceIntent(), null))->toBe([
        'label_fr' => PaymentMethod::CARD->label(),
        'detail_fr' => null,
        'stripe_payment_method_type' => 'card',
    ]);
});
