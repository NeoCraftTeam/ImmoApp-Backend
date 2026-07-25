<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Models\Payment;

/**
 * Consolidates French-facing labels for payment history, exports and Filament —
 * hides raw gateway payloads while exposing enough granularity for audits.
 *
 * Display hierarchy (history UI):
 * - Mobile money → primary « Mobile », secondary operator (Orange Money, MTN…)
 * - Card → primary « Carte », secondary « •••• 4242 »
 * - Wallets (PayPal, Klarna, Apple Pay…) → primary wallet name, secondary last4 when known
 */
final class PaymentPresentation
{
    /**
     * @return array{payment_method_label: string, payment_method_detail: string|null, gateway_label: string}
     */
    public static function forPayment(Payment $payment): array
    {
        [$primary, $detail] = self::resolveMethodDisplay($payment);

        return [
            'payment_method_label' => $primary,
            'payment_method_detail' => $detail,
            'gateway_label' => self::gatewayLabel($payment->gateway),
        ];
    }

    public static function gatewayLabel(?string $gateway): string
    {
        if ($gateway === null || $gateway === '') {
            return '—';
        }

        return PaymentGateway::tryFrom($gateway)?->label() ?? ucfirst($gateway);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function resolveMethodDisplay(Payment $payment): array
    {
        $trace = self::trace($payment);

        if (self::isMobileMoney($payment, $trace)) {
            return ['Mobile', self::resolveMobileOperatorDetail($payment, $trace)];
        }

        if (is_array($trace)) {
            $stripeType = strtolower(trim((string) ($trace['stripe_payment_method_type'] ?? '')));

            if ($stripeType !== '') {
                return self::resolveStripeInstrumentDisplay($stripeType, $trace);
            }

            $labelFr = trim((string) ($trace['label_fr'] ?? ''));
            $detailFr = self::nullableString($trace['detail_fr'] ?? null);

            if ($labelFr !== '') {
                if (self::looksLikeMobileOperatorLabel($labelFr)) {
                    return ['Mobile', self::normalizeMobileOperator($labelFr, $detailFr)];
                }

                if (self::looksLikeCardLabel($labelFr)) {
                    return ['Carte', self::extractLast4FromDetail($detailFr)];
                }

                $walletPrimary = self::normalizeWalletPrimaryLabel($labelFr);

                if ($walletPrimary !== null) {
                    return [$walletPrimary, self::extractLast4FromDetail($detailFr)];
                }
            }
        }

        if ($payment->payment_method === PaymentMethod::CARD) {
            return ['Carte', self::extractCardLast4($payment, $trace)];
        }

        return [
            self::normalizeHistoryLabel(self::fallbackMethodLabel($payment)),
            self::fallbackMethodDetail($payment),
        ];
    }

    /**
     * @param  array<string, mixed>  $trace
     * @return array{0: string, 1: string|null}
     */
    private static function resolveStripeInstrumentDisplay(string $stripeType, array $trace): array
    {
        $detailFr = self::nullableString($trace['detail_fr'] ?? null);
        $last4 = self::extractLast4FromDetail($detailFr);

        return match (true) {
            str_contains($stripeType, 'paypal') => ['PayPal', null],
            str_contains($stripeType, 'klarna') => ['Klarna', null],
            str_contains($stripeType, 'afterpay'),
            str_contains($stripeType, 'affirm'),
            str_contains($stripeType, 'clearpay') => ['Paiement fractionné', null],
            str_contains($stripeType, 'apple') => ['Apple Pay', $last4],
            str_contains($stripeType, 'google') => ['Google Pay', $last4],
            str_contains($stripeType, 'samsung') => ['Samsung Pay', $last4],
            $stripeType === 'link' => ['Stripe Link', $last4],
            str_contains($stripeType, 'amazon') => ['Amazon Pay', null],
            str_contains($stripeType, 'cashapp') => ['Cash App Pay', null],
            str_contains($stripeType, 'ideal') => ['iDEAL', null],
            str_contains($stripeType, 'bancontact') => ['Bancontact', null],
            str_contains($stripeType, 'sepa') => ['Prélèvement SEPA', null],
            str_contains($stripeType, 'alipay') => ['Alipay', null],
            str_contains($stripeType, 'wechat') => ['WeChat Pay', null],
            str_contains($stripeType, 'grabpay') => ['GrabPay', null],
            str_contains($stripeType, 'card') => ['Carte', $last4],
            default => [self::humanizeStripeType($stripeType), $last4],
        };
    }

    /**
     * @param  array<string, mixed>|null  $trace
     */
    private static function isMobileMoney(Payment $payment, ?array $trace): bool
    {
        if ($payment->payment_method === PaymentMethod::MOBILE_MONEY
            || $payment->payment_method === PaymentMethod::ORANGE_MONEY) {
            return true;
        }

        if (!is_array($trace)) {
            return false;
        }

        if (($trace['instrument_family'] ?? null) === 'mobile_money') {
            return true;
        }

        $provider = strtolower((string) ($trace['kpay_provider'] ?? ''));

        return $provider !== ''
            || isset($trace['kpay_provider'])
            || self::looksLikeMobileOperatorLabel((string) ($trace['label_fr'] ?? ''));
    }

    /**
     * @param  array<string, mixed>|null  $trace
     */
    private static function resolveMobileOperatorDetail(Payment $payment, ?array $trace): ?string
    {
        if (is_array($trace)) {
            $detail = self::nullableString($trace['detail_fr'] ?? null);
            if ($detail !== null && !self::looksLikeMaskedPhone($detail)) {
                return self::normalizeMobileOperator('', $detail);
            }

            $provider = (string) ($trace['kpay_provider'] ?? '');
            if ($provider !== '') {
                return self::normalizeMobileOperator($provider, null);
            }

            $label = self::nullableString($trace['label_fr'] ?? null);
            if ($label !== null && self::looksLikeMobileOperatorLabel($label)) {
                return self::normalizeMobileOperator($label, null);
            }
        }

        if ($payment->payment_method === PaymentMethod::ORANGE_MONEY) {
            return 'Orange Money';
        }

        if ($payment->payment_method === PaymentMethod::MOBILE_MONEY) {
            return 'MTN Mobile Money';
        }

        if (filled($payment->phone_number)) {
            return self::maskPhone($payment->phone_number);
        }

        return null;
    }

    private static function normalizeMobileOperator(string $providerOrLabel, ?string $detail): string
    {
        $source = strtolower($detail ?? $providerOrLabel);
        $source = str_replace(['_', '-'], ' ', $source);

        return match (true) {
            str_contains($source, 'orange') => 'Orange Money',
            str_contains($source, 'mtn') => 'MTN Mobile Money',
            str_contains($source, 'moov') => 'Moov Money',
            str_contains($source, 'airtel') => 'Airtel Money',
            str_contains($source, 'mpesa'), str_contains($source, 'vodacom') => 'M-Pesa',
            str_contains($source, 'free') => 'Free Money',
            str_contains($source, 'zamtel') => 'Zamtel Money',
            str_contains($source, 'wave') => 'Wave',
            $detail !== null && $detail !== '' => ucfirst(trim($detail)),
            $providerOrLabel !== '' => ucfirst(str_replace('_', ' ', trim($providerOrLabel))),
            default => 'Mobile Money',
        };
    }

    /**
     * @param  array<string, mixed>|null  $trace
     */
    private static function extractCardLast4(Payment $payment, ?array $trace): ?string
    {
        if (is_array($trace)) {
            $fromDetail = self::extractLast4FromDetail(self::nullableString($trace['detail_fr'] ?? null));
            if ($fromDetail !== null) {
                return $fromDetail;
            }
        }

        $raw = $payment->gateway_response;
        if (!is_array($raw)) {
            return null;
        }

        $trace = $raw['kh_payment_trace'] ?? null;
        if (is_array($trace)) {
            return self::extractLast4FromDetail(self::nullableString($trace['detail_fr'] ?? null));
        }

        return null;
    }

    private static function extractLast4FromDetail(?string $detail): ?string
    {
        if ($detail === null || $detail === '') {
            return null;
        }

        if (preg_match('/(?:•{4}|\.{3}|…)\s*(\d{4})\b/u', $detail, $matches) === 1) {
            return '•••• '.$matches[1];
        }

        if (preg_match('/\b(\d{4})\s*$/', $detail, $matches) === 1) {
            return '•••• '.$matches[1];
        }

        return null;
    }

    private static function looksLikeMobileOperatorLabel(string $label): bool
    {
        $l = strtolower($label);

        return str_contains($l, 'mobile')
            || str_contains($l, 'money')
            || str_contains($l, 'orange')
            || str_contains($l, 'mtn')
            || str_contains($l, 'moov')
            || str_contains($l, 'm-pesa')
            || str_contains($l, 'airtel')
            || str_contains($l, 'genius');
    }

    private static function looksLikeCardLabel(string $label): bool
    {
        $l = strtolower($label);

        return str_contains($l, 'carte') || $l === 'card';
    }

    private static function looksLikeMaskedPhone(string $detail): bool
    {
        return str_starts_with(trim($detail), '···') || str_starts_with(trim($detail), '…');
    }

    private static function normalizeWalletPrimaryLabel(string $labelFr): ?string
    {
        $l = strtolower(trim($labelFr));

        return match (true) {
            str_contains($l, 'paypal') => 'PayPal',
            str_contains($l, 'klarna') => 'Klarna',
            str_contains($l, 'apple pay') => 'Apple Pay',
            str_contains($l, 'google pay') => 'Google Pay',
            str_contains($l, 'stripe link'), $l === 'link' => 'Stripe Link',
            str_contains($l, 'amazon pay') => 'Amazon Pay',
            str_contains($l, 'cash app') => 'Cash App Pay',
            str_contains($l, 'ideal') => 'iDEAL',
            str_contains($l, 'bancontact') => 'Bancontact',
            str_contains($l, 'sepa') => 'Prélèvement SEPA',
            str_contains($l, 'alipay') => 'Alipay',
            str_contains($l, 'wechat') => 'WeChat Pay',
            str_contains($l, 'grabpay') => 'GrabPay',
            str_contains($l, 'fractionné'), str_contains($l, 'buy now') => 'Paiement fractionné',
            default => null,
        };
    }

    private static function humanizeStripeType(string $stripeType): string
    {
        $clean = str_replace(['_', '-'], ' ', strtolower(trim($stripeType)));

        return ucwords($clean);
    }

    private static function normalizeHistoryLabel(string $label): string
    {
        $trimmed = trim($label);

        return match ($trimmed) {
            'Carte bancaire' => 'Carte',
            'MTN Mobile Money', 'MTN Money' => 'Mobile',
            'Orange Money' => 'Mobile',
            'Autre · Kpay', 'Kpay' => 'Mobile',
            default => $trimmed,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function trace(Payment $payment): ?array
    {
        $raw = $payment->gateway_response;

        if (!is_array($raw)) {
            return null;
        }

        $trace = $raw['kh_payment_trace'] ?? null;

        return is_array($trace) ? $trace : null;
    }

    private static function fallbackMethodLabel(Payment $payment): string
    {
        if ($payment->payment_method instanceof PaymentMethod) {
            return match ($payment->payment_method) {
                PaymentMethod::ORANGE_MONEY, PaymentMethod::MOBILE_MONEY => 'Mobile',
                PaymentMethod::CARD => 'Carte',
            };
        }

        return 'Non renseigné';
    }

    private static function fallbackMethodDetail(Payment $payment): ?string
    {
        if ($payment->payment_method === PaymentMethod::ORANGE_MONEY) {
            return 'Orange Money';
        }

        if ($payment->payment_method === PaymentMethod::MOBILE_MONEY) {
            return 'MTN Mobile Money';
        }

        if ($payment->payment_method === PaymentMethod::CARD) {
            return self::extractCardLast4($payment, self::trace($payment));
        }

        return null;
    }

    private static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        $last = mb_substr($digits, -4);

        return '··· '.$last;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
