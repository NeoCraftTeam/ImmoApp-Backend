<?php

declare(strict_types=1);

use App\Support\PaymentReturnUrl;

/**
 * Locks the hosted-checkout return-URL assembly extracted out of
 * {@see PaymentService} into {@see PaymentReturnUrl}. Behaviour is pinned
 * byte-for-byte so the three URL concerns (default PWA return, native bridge,
 * tx_ref preservation) cannot drift after the extraction.
 */
beforeEach(function (): void {
    config()->set('app.frontend_url', 'https://front.test');
    config()->set('app.url', 'https://api.test');
});

// ─── defaultFrontend() ───────────────────────────────────────────────────

it('maps each payment type to its frontend flow', function (string $type, string $flow): void {
    expect(PaymentReturnUrl::defaultFrontend($type, null))
        ->toBe("https://front.test/payment/callback?flow={$flow}");
})->with([
    'credit' => ['credit', 'credit'],
    'unlock' => ['unlock', 'unlock'],
    'subscription' => ['subscription', 'subscription'],
    'boost' => ['boost', 'boost'],
]);

it('falls back to the credit flow for an unknown payment type', function (): void {
    expect(PaymentReturnUrl::defaultFrontend('mystery', null))
        ->toBe('https://front.test/payment/callback?flow=credit');
});

it('appends the ad_id when one is provided', function (): void {
    expect(PaymentReturnUrl::defaultFrontend('unlock', 'ad-123'))
        ->toBe('https://front.test/payment/callback?flow=unlock&ad_id=ad-123');
});

it('omits the ad_id when it is an empty string', function (): void {
    expect(PaymentReturnUrl::defaultFrontend('credit', ''))
        ->toBe('https://front.test/payment/callback?flow=credit');
});

it('trims a trailing slash off the configured frontend url', function (): void {
    config()->set('app.frontend_url', 'https://front.test/');

    expect(PaymentReturnUrl::defaultFrontend('credit', null))
        ->toBe('https://front.test/payment/callback?flow=credit');
});

// ─── nativeBridge() ────────────────────────────────────────────────────────

it('wraps a mobile deep-link in the native-return bridge route', function (): void {
    $deepLink = 'keyhome://payment/return';
    $expectedPath = route('payment.native-return', ['callback' => $deepLink], absolute: false);

    $url = PaymentReturnUrl::nativeBridge($deepLink);

    expect($url)->toEndWith($expectedPath)
        ->and($url)->toStartWith('http');
});

// ─── appendTxRef() ───────────────────────────────────────────────────────

it('appends tx_ref while preserving an existing query', function (): void {
    expect(PaymentReturnUrl::appendTxRef('https://front.test/payment/callback?flow=credit', 'KH-ABC123'))
        ->toBe('https://front.test/payment/callback?flow=credit&tx_ref=KH-ABC123');
});

it('adds tx_ref as the only query when none exists', function (): void {
    expect(PaymentReturnUrl::appendTxRef('https://front.test/payment/callback', 'KH-XYZ'))
        ->toBe('https://front.test/payment/callback?tx_ref=KH-XYZ');
});

it('overrides a pre-existing tx_ref', function (): void {
    expect(PaymentReturnUrl::appendTxRef('https://front.test/return?tx_ref=OLD', 'KH-NEW'))
        ->toBe('https://front.test/return?tx_ref=KH-NEW');
});

it('preserves the fragment when appending tx_ref', function (): void {
    expect(PaymentReturnUrl::appendTxRef('https://front.test/return?a=1#section', 'KH-1'))
        ->toBe('https://front.test/return?a=1&tx_ref=KH-1#section');
});

it('preserves an explicit port', function (): void {
    expect(PaymentReturnUrl::appendTxRef('http://localhost:8000/payment/callback', 'KH-P'))
        ->toBe('http://localhost:8000/payment/callback?tx_ref=KH-P');
});

it('returns the original url unchanged when it cannot be parsed', function (): void {
    expect(PaymentReturnUrl::appendTxRef('http://foo.com:bar', 'KH-1'))
        ->toBe('http://foo.com:bar');
});
