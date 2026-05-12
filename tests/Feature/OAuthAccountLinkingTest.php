<?php

use App\Models\User;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| OAuth Account Linking Confirmation (P4-33)
|--------------------------------------------------------------------------
*/

it('confirms a pending oauth link with a valid token', function (): void {
    $user = User::factory()->create([
        'pending_oauth_provider' => 'google',
        'pending_oauth_id' => 'google-user-123',
        'pending_oauth_avatar' => 'https://example.com/avatar.jpg',
        'pending_oauth_token' => hash('sha256', 'valid-link-token'),
        'pending_oauth_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/oauth/confirm-link', [
        'linking_token' => hash('sha256', 'valid-link-token'),
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['message', 'token', 'user']);

    $fresh = $user->fresh();
    expect($fresh->google_id)->toBe('google-user-123');
    expect($fresh->pending_oauth_token)->toBeNull();
    expect($fresh->pending_oauth_provider)->toBeNull();
});

it('rejects an expired linking token', function (): void {
    User::factory()->create([
        'pending_oauth_provider' => 'google',
        'pending_oauth_id' => 'google-user-999',
        'pending_oauth_token' => hash('sha256', 'expired-token'),
        'pending_oauth_expires_at' => now()->subMinutes(5),
    ]);

    $this->postJson('/api/v1/auth/oauth/confirm-link', [
        'linking_token' => hash('sha256', 'expired-token'),
    ])->assertUnprocessable();
});

it('rejects an invalid linking token', function (): void {
    $this->postJson('/api/v1/auth/oauth/confirm-link', [
        'linking_token' => 'this-token-does-not-exist-anywhere',
    ])->assertUnprocessable();
});

it('rejects confirmation without a token', function (): void {
    $this->postJson('/api/v1/auth/oauth/confirm-link', [])
        ->assertUnprocessable();
});

it('clears all pending oauth fields after confirmation', function (): void {
    $token = hash('sha256', Str::random(32));
    $user = User::factory()->create([
        'pending_oauth_provider' => 'facebook',
        'pending_oauth_id' => 'fb-id-456',
        'pending_oauth_avatar' => null,
        'pending_oauth_token' => $token,
        'pending_oauth_expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson('/api/v1/auth/oauth/confirm-link', [
        'linking_token' => $token,
    ])->assertOk();

    $fresh = $user->fresh();
    expect($fresh->pending_oauth_provider)->toBeNull();
    expect($fresh->pending_oauth_id)->toBeNull();
    expect($fresh->pending_oauth_token)->toBeNull();
    expect($fresh->pending_oauth_expires_at)->toBeNull();
    expect($fresh->facebook_id)->toBe('fb-id-456');
});
