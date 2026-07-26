<?php

declare(strict_types=1);

use App\Models\PersonalAccessToken;
use App\Models\User;

/**
 * Active-session management: list the authenticated user's Sanctum tokens and
 * revoke them remotely (soft-revoke via `revoked_at`).
 */
function bearer(string $plainTextToken): array
{
    return ['Authorization' => 'Bearer '.$plainTextToken];
}

it('lists active sessions and flags the current one', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $current = $user->createToken('owner_token_current', ['api:access']);
    $user->createToken('owner_token_other', ['api:access']);

    $response = $this->withHeaders(bearer($current->plainTextToken))
        ->getJson('/api/v1/my/sessions');

    $response->assertOk()->assertJsonCount(2, 'data');

    $flaggedCurrent = collect($response->json('data'))->firstWhere('is_current', true);
    expect($flaggedCurrent)->not->toBeNull()
        ->and($flaggedCurrent['id'])->toBe($current->accessToken->getKey());
});

it('revokes another session so its token can no longer authenticate', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $current = $user->createToken('s_current', ['api:access']);
    $other = $user->createToken('s_other', ['api:access']);

    $this->withHeaders(bearer($current->plainTextToken))
        ->deleteJson('/api/v1/my/sessions/'.$other->accessToken->getKey())
        ->assertOk();

    // The session is soft-revoked, and Sanctum's resolver now rejects its token
    // (PersonalAccessToken::findToken() filters `revoked_at`), so it can no
    // longer authenticate. Asserted directly to avoid cross-request flakiness.
    expect($other->accessToken->fresh()->revoked_at)->not->toBeNull();
    expect(PersonalAccessToken::findToken($other->plainTextToken))->toBeNull();
});

it('refuses to revoke the current session', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $current = $user->createToken('s_current', ['api:access']);

    $this->withHeaders(bearer($current->plainTextToken))
        ->deleteJson('/api/v1/my/sessions/'.$current->accessToken->getKey())
        ->assertStatus(422);
});

it('revokes all other sessions but keeps the current one', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $current = $user->createToken('s1', ['api:access']);
    $user->createToken('s2', ['api:access']);
    $user->createToken('s3', ['api:access']);

    $this->withHeaders(bearer($current->plainTextToken))
        ->deleteJson('/api/v1/my/sessions')
        ->assertOk()
        ->assertJsonPath('count', 2);

    $this->withHeaders(bearer($current->plainTextToken))
        ->getJson('/api/v1/my/sessions')
        ->assertJsonCount(1, 'data');
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/my/sessions')->assertUnauthorized();
});
