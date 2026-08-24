<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentMethod;
use Stripe\Charge;
use Stripe\PaymentIntent;
use Stripe\StripeObject;

/**
 * Derives the stable French-facing payment-method labels ("kh_payment_trace")
 * that KeyHome pins onto a succeeded Stripe PaymentIntent for receipts and
 * reconciliation.
 *
 * Pure translation of Stripe `payment_method_details` / `payment_method_types`
 * into `{label_fr, detail_fr, stripe_payment_method_type}` — no network calls,
 * no instance state. Extracted from StripePaymentService so the labelling
 * logic can be unit-tested in isolation.
 */
final class StripePaymentTrace
{
    /**
     * @return array{label_fr: string, detail_fr: ?string, stripe_payment_method_type: string}
     */
    public static function build(PaymentIntent $intent, ?Charge $charge): array
    {
        $pmdType = '';

        if (($charge?->payment_method_details) instanceof StripeObject) {
            $pmdType = strtolower((string) (($charge->payment_method_details->__get('type')
                ?? $charge->payment_method_details->type) ?: ''));
        }

        if ($pmdType === '') {
            foreach ($intent->payment_method_types ?? [] as $t) {
                if ($t === '') {
                    continue;
                }

                $low = strtolower((string) $t);

                if (str_contains($low, 'paypal')) {
                    return [
                        'label_fr' => 'PayPal',
                        'detail_fr' => null,
                        'stripe_payment_method_type' => 'paypal',
                    ];
                }

                if ($low === 'link') {
                    return [
                        'label_fr' => 'Stripe Link',
                        'detail_fr' => null,
                        'stripe_payment_method_type' => 'link',
                    ];
                }

                $pmdType = $low;

                break;
            }
        }

        if (str_contains($pmdType, 'paypal')) {
            return [
                'label_fr' => 'PayPal',
                'detail_fr' => null,
                'stripe_payment_method_type' => 'paypal',
            ];
        }

        if ($pmdType === 'link') {
            return [
                'label_fr' => 'Stripe Link',
                'detail_fr' => null,
                'stripe_payment_method_type' => 'link',
            ];
        }

        $fromCharge = self::stripeFrenchTraceFromCharge($charge);

        return [
            'label_fr' => $fromCharge['label_fr'],
            'detail_fr' => $fromCharge['detail_fr'],
            'stripe_payment_method_type' => $pmdType !== '' ? $pmdType : 'card',
        ];
    }

    /**
     * Stable French-facing labels driven by Stripe `payment_method_details`.
     *
     * @return array{label_fr: string, detail_fr: ?string}
     */
    private static function stripeFrenchTraceFromCharge(?Charge $charge): array
    {
        $details = $charge?->payment_method_details;

        if ($details === null) {
            return [
                'label_fr' => PaymentMethod::CARD->label(),
                'detail_fr' => null,
            ];
        }

        $pmType = (string) (($details->__get('type') ?? $details->type) ?: '');

        return match ($pmType) {
            'paypal', 'paypal_express_checkout', 'paypal_v2', 'paypal_billing_agreement', 'paypal_v3' => [
                'label_fr' => 'PayPal',
                'detail_fr' => null,
            ],
            'amazon_pay', 'amazonpay' => [
                'label_fr' => 'Amazon Pay',
                'detail_fr' => null,
            ],
            'cashapp', 'cashapp_pay' => [
                'label_fr' => 'Cash App Pay',
                'detail_fr' => null,
            ],
            'link' => [
                'label_fr' => 'Stripe Link',
                'detail_fr' => null,
            ],
            'ideal' => [
                'label_fr' => 'iDEAL',
                'detail_fr' => null,
            ],
            'bancontact' => [
                'label_fr' => 'Bancontact',
                'detail_fr' => null,
            ],
            'klarna', 'afterpay_clearpay', 'affirm', 'clearpay_instalments' => [
                'label_fr' => str_contains($pmType, 'klarna') ? 'Klarna' : 'Fractionné (Buy now pay later)',
                'detail_fr' => null,
            ],
            'sepa_debit' => [
                'label_fr' => 'Prélèvement SEPA',
                'detail_fr' => null,
            ],
            'apple_pay_card', 'facebook_pay_card' => [
                'label_fr' => str_contains($pmType, 'apple') ? 'Apple Pay' : 'Carte',
                'detail_fr' => null,
            ],
            'grabpay' => [
                'label_fr' => 'GrabPay',
                'detail_fr' => null,
            ],
            'alipay' => [
                'label_fr' => 'Alipay',
                'detail_fr' => null,
            ],
            'wechat_pay' => [
                'label_fr' => 'WeChat Pay',
                'detail_fr' => null,
            ],
            'paynow', 'fpx', 'fpx_kfp' => [
                'label_fr' => 'Virement instantané (régional)',
                'detail_fr' => null,
            ],
            'card' => self::stripeCardLikeTrace($details, $pmType),
            default => [
                'label_fr' => self::stripeGenericInstrumentLabelFr($pmType),
                'detail_fr' => null,
            ],
        };
    }

    /**
     * @return array{label_fr: string, detail_fr: ?string}
     */
    private static function stripeCardLikeTrace(?StripeObject $details, string $pmType): array
    {
        if ($details === null) {
            return ['label_fr' => PaymentMethod::CARD->label(), 'detail_fr' => null];
        }

        $card = $details->card ?? null;
        $wallet = $card instanceof StripeObject ? ($card->wallet ?? null) : null;
        $walletType = $wallet instanceof StripeObject ? (string) ($wallet->type ?? '') : '';

        $brandRaw = '';
        if ($card instanceof StripeObject && isset($card->brand)) {
            $brandRaw = (string) $card->brand;
        }

        $last4 = $card instanceof StripeObject && isset($card->last4)
            ? preg_replace('/\D/', '', (string) $card->last4) : '';

        $detailFromCard = $brandRaw !== '' && $last4 !== ''
            ? sprintf('%s · •••• %s', self::stripeCardBrandFr($brandRaw), $last4)
            : ($brandRaw !== '' ? self::stripeCardBrandFr($brandRaw) : null);

        return match ($walletType) {
            'apple_pay' => ['label_fr' => 'Apple Pay', 'detail_fr' => $detailFromCard],
            'google_pay' => ['label_fr' => 'Google Pay', 'detail_fr' => $detailFromCard],
            'link' => ['label_fr' => 'Stripe Link', 'detail_fr' => $detailFromCard],
            'samsung_pay' => ['label_fr' => 'Samsung Pay', 'detail_fr' => $detailFromCard],
            'cashapp_pay' => ['label_fr' => 'Cash App Pay', 'detail_fr' => null],
            default => ['label_fr' => PaymentMethod::CARD->label(), 'detail_fr' => $detailFromCard],
        };
    }

    private static function stripeGenericInstrumentLabelFr(string $stripeType): string
    {
        $stripeType = trim(strtolower(str_replace('_', '-', $stripeType)));

        $map = [
            'google-pay' => 'Google Pay',
            'apple-pay' => 'Apple Pay',
            'sepa-direct-debit' => 'Prélèvement SEPA',
        ];

        return $map[$stripeType] ?? 'Paiement en ligne '.$stripeType;
    }

    private static function stripeCardBrandFr(string $brand): string
    {
        $b = strtolower($brand);

        return match ($b) {
            'visa', 'electron' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex', 'american_express' => 'American Express',
            'diners' => 'Diners Club',
            'discover', 'eftpos_au', 'china_union_pay', 'jcb', 'rupay', 'eftpos_au' => ucfirst(str_replace('_', ' ', $b)),
            default => ucfirst($b ?: 'carte'),
        };
    }
}
