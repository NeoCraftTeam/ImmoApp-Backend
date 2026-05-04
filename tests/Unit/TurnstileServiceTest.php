<?php

declare(strict_types=1);

use App\Services\TurnstileService;

it('does not treat Cloudflare dummy visible secret as enforcing Turnstile', function (): void {
    config(['services.turnstile.secret_key' => '1x0000000000000000000000000000000AA']);

    $svc = app(TurnstileService::class);

    expect($svc->isConfigured())->toBeFalse();
    expect($svc->verify(null))->toBeTrue();
    expect($svc->verify(''))->toBeTrue();
});

it('treats empty secret as Turnstile disabled', function (): void {
    config(['services.turnstile.secret_key' => '']);

    $svc = app(TurnstileService::class);

    expect($svc->isConfigured())->toBeFalse();
    expect($svc->verify(null))->toBeTrue();
});

it('treats non-empty production-like secret as enforcing', function (): void {
    config(['services.turnstile.secret_key' => '0x'.str_repeat('a', 60)]);

    $svc = app(TurnstileService::class);

    expect($svc->isConfigured())->toBeTrue();
    expect($svc->verify(null))->toBeFalse();
    expect($svc->verify(''))->toBeFalse();
});
