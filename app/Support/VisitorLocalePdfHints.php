<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves the optional visitor-locale currency hints (currency + exchange
 * rate + display symbol) from a PDF request's query string.
 *
 * Visitors browsing from abroad may ask the receipt/export PDFs to show a
 * converted amount alongside the base total. The conversion is opt-in and
 * validated here: only a whitelisted currency paired with a finite, strictly
 * positive rate is honoured — anything else falls back to no locale hint.
 *
 * Extracted from PaymentController so the controller keeps only HTTP wiring.
 */
final class VisitorLocalePdfHints
{
    /**
     * ISO 4217 codes accepted by {@see fromRequest()} mapped to a display symbol for PDF receipts.
     *
     * @var array<string, string>
     */
    private const array SYMBOL_BY_CCY = [
        'EUR' => '€',
        'USD' => '$',
        'CAD' => '$',
        'AUD' => '$',
        'MXN' => '$',
        'BRL' => '$',
        'GBP' => '£',
        'CHF' => 'CHF',
        'JPY' => '¥',
        'CNY' => '¥',
        'KRW' => '₩',
    ];

    /**
     * @return array{
     *     localeCurrency: string|null,
     *     localeRate: float|null,
     *     localeSymbol: string|null
     * }
     */
    public static function fromRequest(Request $request): array
    {
        $allowedCurrencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'JPY', 'MXN', 'BRL', 'CNY', 'AUD', 'KRW'];
        $rawCurrency = strtoupper((string) $request->query('currency', ''));
        $rawRate = (float) $request->query('rate', 0);
        $useLocale = in_array($rawCurrency, $allowedCurrencies, true)
            && is_finite($rawRate)
            && $rawRate > 0;

        if (!$useLocale) {
            return [
                'localeCurrency' => null,
                'localeRate' => null,
                'localeSymbol' => null,
            ];
        }

        return [
            'localeCurrency' => $rawCurrency,
            'localeRate' => $rawRate,
            'localeSymbol' => self::SYMBOL_BY_CCY[$rawCurrency],
        ];
    }
}
