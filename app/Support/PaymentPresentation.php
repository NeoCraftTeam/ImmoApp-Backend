<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Models\Payment;

/**
 * Consolidates French-facing labels for payment history, exports and Filament —
 * hides raw gateway payloads while exposing enough granularity for audits.
 */
final class PaymentPresentation
{
    /**
     * @return array{payment_method_label: string, payment_method_detail: string|null, gateway_label: string}
     */
    public static function forPayment(Payment $payment): array
    {
        $gatewayLabel = self::gatewayLabel($payment->gateway);
        $trace = self::trace($payment);

        if (is_array($trace)) {
            $label = isset($trace['label_fr']) && is_string($trace['label_fr']) && $trace['label_fr'] !== ''
                ? $trace['label_fr']
                : (self::inferLabelFromTrace($trace) ?? self::fallbackMethodLabel($payment));

            $detail = isset($trace['detail_fr']) && is_string($trace['detail_fr']) && $trace['detail_fr'] !== ''
                ? $trace['detail_fr']
                : null;

            if ($detail === null) {
                $detail = self::fallbackMethodDetail($payment);
            }

            return [
                'payment_method_label' => self::normalizeHistoryLabel($label),
                'payment_method_detail' => $detail,
                'gateway_label' => $gatewayLabel,
            ];
        }

        return [
            'payment_method_label' => self::normalizeHistoryLabel(self::fallbackMethodLabel($payment)),
            'payment_method_detail' => self::fallbackMethodDetail($payment),
            'gateway_label' => $gatewayLabel,
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
     * @param  array<string, mixed>  $trace
     */
    private static function inferLabelFromTrace(array $trace): ?string
    {
        $stripeType = $trace['stripe_payment_method_type'] ?? null;
        if (is_string($stripeType) && $stripeType !== '') {
            return self::labelForStripePaymentMethodType($stripeType);
        }

        return null;
    }

    private static function labelForStripePaymentMethodType(string $type): ?string
    {
        $t = strtolower(trim($type));

        return match (true) {
            str_contains($t, 'paypal') => 'PayPal',
            $t === 'link' => 'Stripe Link',
            $t === 'card' || str_contains($t, 'card') => 'Carte',
            default => null,
        };
    }

    private static function normalizeHistoryLabel(string $label): string
    {
        $trimmed = trim($label);

        return match ($trimmed) {
            'Carte bancaire' => 'Carte',
            'MTN Mobile Money' => 'MTN Money',
            'Autre · Flutterwave' => 'Autres',
            'Autre · GeniusPay' => 'Autres',
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
                PaymentMethod::ORANGE_MONEY => 'Orange Money',
                PaymentMethod::MOBILE_MONEY => 'MTN Money',
                PaymentMethod::CARD => 'Carte',
                PaymentMethod::FLUTTERWAVE => 'Autres',
            };
        }

        return 'Non renseigné';
    }

    private static function fallbackMethodDetail(Payment $payment): ?string
    {
        if (($payment->payment_method === PaymentMethod::MOBILE_MONEY || $payment->payment_method === PaymentMethod::ORANGE_MONEY)
            && filled($payment->phone_number)) {
            return self::maskPhone($payment->phone_number);
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
}
