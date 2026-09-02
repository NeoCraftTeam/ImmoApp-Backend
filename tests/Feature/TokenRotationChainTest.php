<?php

declare(strict_types=1);

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * Rotation chain of a single SPA session.
 *
 * The web app rotates its Sanctum token twice on an ordinary visit: once when
 * `/auth/login` issues it, then again on every `/auth/refresh`. AUTH-5 flags a
 * family compromise when no *active* token matches the rotation pattern while a
 * revoked ancestor does — and that branch revokes every session the user owns.
 * So each rotation has to leave an active token the same pattern still matches;
 * otherwise the second refresh of a legitimate session logs the user out
 * everywhere, and the very next authenticated call answers 401 UNAUTHENTICATED.
 */
function loginAndGetToken(User $user, string $password = 'Password123@'): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertOk();

    return (string) $response->json('access_token');
}

function verifiedCustomer(): User
{
    return User::factory()->customers()->create([
        'password' => bcrypt('Password123@'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
}

it('still authenticates after two consecutive refreshes', function (): void {
    $user = verifiedCustomer();

    $loginToken = loginAndGetToken($user);

    $firstRefresh = $this->withHeader('Authorization', "Bearer {$loginToken}")
        ->postJson('/api/v1/auth/refresh');
    $firstRefresh->assertOk();

    $secondRefresh = $this->withHeader('Authorization', "Bearer {$firstRefresh->json('access_token')}")
        ->postJson('/api/v1/auth/refresh');
    $secondRefresh->assertOk();

    $this->withHeader('Authorization', "Bearer {$secondRefresh->json('access_token')}")
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

// BUG CATCH: `/auth/refresh` rotated on `{prefix}_token_%` while naming its own
// output `{prefix}_refreshed_…`, so the pattern could never match what the
// rotation had just produced. The second refresh therefore saw "no active
// token + a revoked ancestor" and raised a compromise on a healthy session.
it('never raises a token family compromise for login then two refreshes', function (): void {
    $user = verifiedCustomer();
    $loginToken = loginAndGetToken($user);

    Log::spy();

    $firstRefresh = $this->withHeader('Authorization', "Bearer {$loginToken}")
        ->postJson('/api/v1/auth/refresh');

    $this->withHeader('Authorization', "Bearer {$firstRefresh->json('access_token')}")
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();

    Log::shouldNotHaveReceived('alert');
});

// BUG CATCH: the compromise branch revokes *every* active token of the user, so
// a false positive on the web session silently signed the same account out of
// the mobile app and of the OAuth session.
it('keeps other surfaces signed in while the web session refreshes', function (): void {
    $user = verifiedCustomer();
    $mobileToken = app(TokenService::class)->createForUser($user, 'clerk')->plainTextToken;

    $loginToken = loginAndGetToken($user);

    $firstRefresh = $this->withHeader('Authorization', "Bearer {$loginToken}")
        ->postJson('/api/v1/auth/refresh');

    $this->withHeader('Authorization', "Bearer {$firstRefresh->json('access_token')}")
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$mobileToken}")
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

// The genuine RTR case must keep working: a token that was rotated away is dead
// and Sanctum refuses to resolve it, so it can never mint a successor.
it('marks a rotated-away token as unusable', function (): void {
    $user = verifiedCustomer();
    $loginToken = loginAndGetToken($user);

    $this->withHeader('Authorization', "Bearer {$loginToken}")
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();

    expect(PersonalAccessToken::findToken($loginToken))->toBeNull();
});
