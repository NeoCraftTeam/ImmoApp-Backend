<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('exposes auth config with clerk and socialite availability', function (): void {
    Config::set('clerk.publishable_key', 'pk_test_example');
    Config::set('services.google.client_id', '');
    Config::set('services.facebook.client_id', 'fb-id');
    Config::set('services.github.client_id', '');

    $response = $this->getJson('/api/v1/config/auth');

    $response->assertSuccessful()
        ->assertJsonPath('data.clerk.enabled', true)
        ->assertJsonPath('data.clerk.publishable_key', 'pk_test_example')
        ->assertJsonPath('data.clerk.oauth_providers', ['google', 'facebook', 'github'])
        ->assertJsonPath('data.socialite.google', false)
        ->assertJsonPath('data.socialite.facebook', true)
        ->assertJsonPath('data.google.method', 'clerk');
});

it('returns socialite method for google when client id is configured', function (): void {
    Config::set('clerk.publishable_key', 'pk_test_example');
    Config::set('services.google.client_id', 'google-client-id');

    $response = $this->getJson('/api/v1/config/auth');

    $response->assertSuccessful()
        ->assertJsonPath('data.google.method', 'socialite')
        ->assertJsonPath('data.socialite.google', true);
});

it('rejects socialite redirect when provider is not configured', function (): void {
    Config::set('services.google.client_id', '');
    Config::set('clerk.publishable_key', 'pk_test_example');

    $response = $this->getJson('/api/v1/auth/oauth/google/redirect', [
        'X-KeyHome-Client' => 'keyhome-mobile-visitors',
        'redirect_uri' => 'keyhome://auth/callback',
    ]);

    $response->assertStatus(503)
        ->assertJsonPath('code', 'OAUTH_PROVIDER_NOT_CONFIGURED');
});
