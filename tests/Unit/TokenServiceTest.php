<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// createForUser
// ---------------------------------------------------------------------------

it('creates a client-prefixed token for a customer', function (): void {
    $user = User::factory()->customers()->create();
    $service = app(TokenService::class);

    $token = $service->createForUser($user, 'auth');

    expect($token->plainTextToken)->toBeString()->not->toBeEmpty();
    expect($token->accessToken->name)->toStartWith('client_auth_');
    expect($token->accessToken->abilities)->toContain('role:customer', 'api:access');
    expect($token->accessToken->expires_at)->not->toBeNull();
});

it('creates an owner-prefixed token for an agent', function (): void {
    $user = User::factory()->agents()->create();
    $service = app(TokenService::class);

    $token = $service->createForUser($user, 'clerk');

    expect($token->accessToken->name)->toStartWith('owner_clerk_');
    expect($token->accessToken->abilities)->toContain('role:agent', 'api:access');
});

it('creates a client-prefixed token for an admin', function (): void {
    $user = User::factory()->admin()->create();
    $service = app(TokenService::class);

    $token = $service->createForUser($user, 'registration');

    expect($token->accessToken->name)->toStartWith('client_registration_');
    expect($token->accessToken->abilities)->toContain('role:admin', 'api:access');
});

it('sets token expiry aligned with sanctum.expiration', function (): void {
    $user = User::factory()->customers()->create();
    $service = app(TokenService::class);

    $token = $service->createForUser($user, 'test');

    $expectedMinutes = (int) config('sanctum.expiration');
    expect($token->accessToken->expires_at)->not->toBeNull();
    expect(now()->diffInMinutes($token->accessToken->expires_at))->toBeBetween(
        $expectedMinutes - 2,
        $expectedMinutes + 2
    );
});

// ---------------------------------------------------------------------------
// rotateForUser
// ---------------------------------------------------------------------------

it('revokes matching tokens before creating a new one', function (): void {
    $user = User::factory()->agents()->create();
    $service = app(TokenService::class);

    // Create two old tokens that match the pattern
    $user->createToken('owner_token_111', ['role:agent', 'api:access'], now()->addDay());
    $user->createToken('owner_token_222', ['role:agent', 'api:access'], now()->addDay());
    // One unrelated token that should survive
    $user->createToken('client_other_333', ['role:agent', 'api:access'], now()->addDay());

    expect($user->tokens()->count())->toBe(3);

    $newToken = $service->rotateForUser($user, 'token', 'owner_token_%');

    // Matching tokens are soft-revoked (revoked_at set), not hard-deleted.
    // Active tokens: client_other_333 + new owner_token_* = 2.
    expect($user->tokens()->whereNull('revoked_at')->count())->toBe(2);
    expect($user->tokens()->where('name', 'like', 'client_other_%')->whereNull('revoked_at')->exists())->toBeTrue();
    expect($user->tokens()->where('name', 'like', 'owner_token_%')->whereNotNull('revoked_at')->count())->toBe(2);
    expect($newToken->accessToken->name)->toStartWith('owner_token_');
});

it('skips revocation when revokePattern is null', function (): void {
    $user = User::factory()->customers()->create();
    $service = app(TokenService::class);

    $user->createToken('client_old_111', ['role:customer', 'api:access'], now()->addDay());

    $newToken = $service->rotateForUser($user, 'fresh');

    // Old token kept + new token = 2
    expect($user->tokens()->count())->toBe(2);
    expect($newToken->accessToken->name)->toStartWith('client_fresh_');
});
