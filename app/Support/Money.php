<?php

declare(strict_types=1);

namespace App\Support;

use NumberFormatter;

/**
 * Currency-aware money formatter.
 *
 * Resolves locale, decimals and symbol from `config/locale.php :: currencies`
 * so the same code formats XAF, EUR, JPY or any other supported currency
 * without per-call configuration.
 *
 *   Money::format(150_000, 'XAF')          // "150 000 FCFA"
 *   Money::format(1_499.99, 'EUR')         // "1 499,99 €"
 *   Money::format(1_499.99, 'USD', 'en')   // "$1,499.99"
 *
 * Falls back to a portable "{value} {code}" rendering when ext-intl is
 * missing (CI containers without intl will not crash).
 */
final readonly class Money
{
    public static function format(int|float $amount, string $currency, ?string $locale = null): string
    {
        $currency = strtoupper($currency);

        /** @var array<string, array{locale: string, decimals: int, symbol: string}> $map */
        $map = (array) config('locale.currencies', []);
        $meta = $map[$currency] ?? null;

        if ($meta === null) {
            // Unknown currency — render plainly so we never crash on legacy data.
            return number_format((float) $amount, 0, '.', ' ').' '.$currency;
        }

        $resolvedLocale = $locale !== null
            ? self::resolveLocale($locale, $meta['locale'])
            : $meta['locale'];

        if (!class_exists(NumberFormatter::class)) {
            return number_format((float) $amount, $meta['decimals'], ',', ' ').' '.$meta['symbol'];
        }

        $formatter = new NumberFormatter($resolvedLocale, NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $meta['decimals']);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $meta['decimals']);

        $formatted = $formatter->formatCurrency((float) $amount, $currency);

        return $formatted === false ? (string) $amount.' '.$currency : $formatted;
    }

    /**
     * If the user-locale (e.g. `en`) is incompatible with the currency-locale
     * (e.g. `fr_CM`), keep the currency-locale so XAF still prints "FCFA"
     * with French digit grouping. Otherwise stitch the user-locale's region.
     */
    private static function resolveLocale(string $userLocale, string $currencyLocale): string
    {
        $short = strtolower(substr($userLocale, 0, 2));
        $currencyShort = strtolower(substr($currencyLocale, 0, 2));

        return $short === $currencyShort ? $userLocale : $currencyLocale;
    }
}
