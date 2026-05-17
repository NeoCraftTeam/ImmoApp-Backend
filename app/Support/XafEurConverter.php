<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Currency conversion between XAF (CFA franc BEAC) and EUR cents.
 *
 * Stripe does not support XAF/XOF natively. KeyHome bills in EUR using the
 * official BEAC peg: 1 EUR = 655.957 XAF. The XAF amount is always the
 * canonical figure stored in `payments.amount`; the EUR equivalent is only
 * used for Stripe PaymentIntent creation and receipt reconciliation.
 *
 * The rate can be overridden via `services.stripe.xaf_to_eur_rate` in case
 * the peg is ever adjusted or a test value is needed.
 */
final class XafEurConverter
{
    /** Official BEAC peg (1 EUR = 655.957 XAF). */
    private const float DEFAULT_RATE = 655.957;

    /**
     * Convert an XAF amount (whole francs) to EUR cents (smallest Stripe unit).
     *
     * @param  float  $xafAmount  Amount in XAF francs
     * @return int Amount in EUR cents
     */
    public static function toEurCents(float $xafAmount): int
    {
        $rate = self::rate();

        // XAF → EUR → cents. Round to nearest cent.
        return (int) round(($xafAmount / $rate) * 100);
    }

    /**
     * Convert EUR cents back to XAF (whole francs).
     *
     * @param  int  $eurCents  Amount in EUR cents
     * @return float Amount in XAF francs
     */
    public static function toXaf(int $eurCents): float
    {
        $rate = self::rate();

        return round(($eurCents / 100) * $rate, 0);
    }

    /**
     * Return the active conversion rate (config overridable).
     */
    public static function rate(): float
    {
        $rate = (float) config('services.stripe.xaf_to_eur_rate', self::DEFAULT_RATE);

        return $rate > 0 ? $rate : self::DEFAULT_RATE;
    }
}
