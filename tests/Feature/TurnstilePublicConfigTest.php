<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('returns turnstile site key when configured', function (): void {
    Config::set('services.turnstile.site_key', 'test-turnstile-site-key');
    Config::set('services.turnstile.secret_key', 'sk-test-non-dummy-secret-value-xxxxxxxx');

    $this->getJson('/api/v1/config/turnstile')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.site_key', 'test-turnstile-site-key')
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonPath('data.show_credits_turnstile', true);
});

it('returns null site key when not configured', function (): void {
    Config::set('services.turnstile.site_key', '');
    Config::set('services.turnstile.secret_key', '');

    $this->getJson('/api/v1/config/turnstile')
        ->assertSuccessful()
        ->assertJsonPath('data.site_key', null)
        ->assertJsonPath('data.show_credits_turnstile', false);
});

it('exposes show_credits_turnstile in testing with dummy keys without verification_required', function (): void {
    Config::set('services.turnstile.site_key', '1x00000000000000000000AA');
    Config::set('services.turnstile.secret_key', '1x0000000000000000000000000000000AA');

    $this->getJson('/api/v1/config/turnstile')
        ->assertSuccessful()
        ->assertJsonPath('data.verification_required', false)
        ->assertJsonPath('data.show_credits_turnstile', true)
        ->assertJsonPath('data.site_key', '1x00000000000000000000AA');
});
