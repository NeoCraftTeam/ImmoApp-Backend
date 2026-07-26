<?php

declare(strict_types=1);

use App\Support\FrontendRedirectGuard;

beforeEach(function (): void {
    config()->set('app.frontend_url', 'https://keyhome.app');
    config()->set('app.oauth_allowed_redirect_hosts', '');
    config()->set('app.oauth_allowed_redirect_schemes', 'keyhome,keyhomeowners');
});

it('accepts the web frontend host over https', function (): void {
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl('https://keyhome.app/payment/callback'))->toBeTrue();
});

it('rejects an arbitrary https host', function (): void {
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl('https://evil.example.com/callback'))->toBeFalse();
});

it('accepts a subdomain of the allowed frontend host', function (): void {
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl('https://app.keyhome.app/payment/callback'))->toBeTrue();
});

it('rejects a look-alike host that merely ends with the allowed host', function (): void {
    // `notkeyhome.app` ne doit PAS matcher `keyhome.app` (le check exige un
    // séparateur `.` de sous-domaine).
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl('https://notkeyhome.app/callback'))->toBeFalse();
});

it('accepts the mobile deep-link schemes', function (string $uri): void {
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl($uri))->toBeTrue();
})->with([
    'keyhome' => 'keyhome://credits/callback',
    'keyhomeowners' => 'keyhomeowners://credits/callback',
    'expo go' => 'exp://192.168.1.10:8081/--/credits/callback',
]);

it('rejects a scheme that is not whitelisted', function (): void {
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl('malicious://credits/callback'))->toBeFalse();
});

it('rejects an empty or oversized uri', function (): void {
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl(''))->toBeFalse();
    expect(FrontendRedirectGuard::isAllowedAbsoluteUrl('keyhome://'.str_repeat('a', 3000)))->toBeFalse();
});
