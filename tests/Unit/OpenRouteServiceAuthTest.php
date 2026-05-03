<?php

declare(strict_types=1);

use App\Support\OpenRouteServiceAuth;

it('prefixes Bearer for the ORS cloud API by default', function (): void {
    config(['services.ors.authorization_raw' => false]);

    expect(OpenRouteServiceAuth::authorizationHeader('secret-token'))->toBe('Bearer secret-token');
});

it('preserves an existing Bearer prefix', function (): void {
    config(['services.ors.authorization_raw' => false]);

    expect(OpenRouteServiceAuth::authorizationHeader('Bearer abc'))->toBe('Bearer abc');
});

it('sends raw authorization when ORS_AUTHORIZATION_RAW is enabled', function (): void {
    config(['services.ors.authorization_raw' => true]);

    expect(OpenRouteServiceAuth::authorizationHeader('raw-key'))->toBe('raw-key');
});

it('returns empty string for empty key', function (): void {
    expect(OpenRouteServiceAuth::authorizationHeader(''))->toBe('');
});
