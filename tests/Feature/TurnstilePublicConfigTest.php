<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('returns turnstile site key when configured', function (): void {
    Config::set('services.turnstile.site_key', 'test-turnstile-site-key');

    $this->getJson('/api/v1/config/turnstile')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.site_key', 'test-turnstile-site-key');
});

it('returns null site key when not configured', function (): void {
    Config::set('services.turnstile.site_key', '');

    $this->getJson('/api/v1/config/turnstile')
        ->assertSuccessful()
        ->assertJsonPath('data.site_key', null);
});
