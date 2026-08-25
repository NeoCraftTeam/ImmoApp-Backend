<?php

declare(strict_types=1);

use App\Support\VisitorLocalePdfHints;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| VisitorLocalePdfHints
|--------------------------------------------------------------------------
| White-box coverage of every branch of the locale-hint resolver extracted
| from PaymentController — the symbol lookup and each null fallback are not
| observable through the binary PDF the export/receipt endpoints return.
*/

function hintsFor(array $query): array
{
    return VisitorLocalePdfHints::fromRequest(Request::create('/', 'GET', $query));
}

it('resolves a whitelisted currency with a positive rate to its symbol', function (string $currency, string $symbol): void {
    expect(hintsFor(['currency' => $currency, 'rate' => 0.0015]))->toBe([
        'localeCurrency' => $currency,
        'localeRate' => 0.0015,
        'localeSymbol' => $symbol,
    ]);
})->with([
    'EUR' => ['EUR', '€'],
    'USD' => ['USD', '$'],
    'CAD' => ['CAD', '$'],
    'AUD' => ['AUD', '$'],
    'MXN' => ['MXN', '$'],
    'BRL' => ['BRL', '$'],
    'GBP' => ['GBP', '£'],
    'CHF' => ['CHF', 'CHF'],
    'JPY' => ['JPY', '¥'],
    'CNY' => ['CNY', '¥'],
    'KRW' => ['KRW', '₩'],
]);

it('uppercases the requested currency before matching', function (): void {
    expect(hintsFor(['currency' => 'eur', 'rate' => 1.5]))->toBe([
        'localeCurrency' => 'EUR',
        'localeRate' => 1.5,
        'localeSymbol' => '€',
    ]);
});

it('falls back to no hint for unsupported or missing input', function (array $query): void {
    expect(hintsFor($query))->toBe([
        'localeCurrency' => null,
        'localeRate' => null,
        'localeSymbol' => null,
    ]);
})->with([
    'unknown currency' => [['currency' => 'XYZ', 'rate' => 1.0]],
    'empty currency' => [['currency' => '', 'rate' => 1.0]],
    'missing currency' => [['rate' => 1.0]],
    'zero rate' => [['currency' => 'EUR', 'rate' => 0]],
    'negative rate' => [['currency' => 'EUR', 'rate' => -1.0]],
    'missing rate' => [['currency' => 'EUR']],
    'no query at all' => [[]],
]);
