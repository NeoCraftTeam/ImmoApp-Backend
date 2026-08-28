<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Multi-panel session isolation: a single browser can hold both an owner and a
 * client login. The shared stateful session cookie only ever holds the
 * last-authenticated user, so without PreferBearerOverSession a client-context
 * request carrying a valid client Bearer would resolve to the owner session and
 * leak the wrong profile across panels.
 */
it('lets a client Bearer win over an owner session cookie on /auth/me', function (): void {
    $owner = User::factory()->agents()->create();
    $client = User::factory()->customers()->create();

    $clientToken = $client->createToken('client-device')->plainTextToken;

    // Owner holds the shared stateful session cookie; the client carries the Bearer.
    $response = $this->actingAs($owner, 'web')
        ->withToken($clientToken)
        ->getJson('/api/v1/auth/me');

    $response->assertOk();
    expect($response->json('data.id'))->toBe($client->id)
        ->and($response->json('data.id'))->not->toBe($owner->id);
});

it('falls back to the session cookie when no Bearer is present', function (): void {
    // Post-reload bootstrap: in-memory Bearer tokens are gone, only the session
    // cookie remains. Login must still persist across the reload.
    $owner = User::factory()->agents()->create();

    $response = $this->actingAs($owner, 'web')
        ->getJson('/api/v1/auth/me');

    $response->assertOk();
    expect($response->json('data.id'))->toBe($owner->id);
});

it('does not silently downgrade an invalid Bearer to the session user', function (): void {
    // A present-but-invalid Bearer must 401 rather than resolve to the session
    // owner — surfacing the wrong identity would be worse than a clean rejection.
    $owner = User::factory()->agents()->create();

    $response = $this->actingAs($owner, 'web')
        ->withToken('this-is-not-a-valid-sanctum-token')
        ->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});
