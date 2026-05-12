<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function createVerifiedUser(string $role = 'customer', string $password = 'Secret@123'): User
{
    $factory = match ($role) {
        'agent' => User::factory()->agents(),
        'admin' => User::factory()->admin(),
        default => User::factory()->customers(),
    };

    return $factory->create([
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
}

// ---------------------------------------------------------------------------
// Happy paths
// ---------------------------------------------------------------------------

it('logs in a customer with valid credentials', function (): void {
    $user = createVerifiedUser('customer');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'client',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'access_token', 'expires_at', 'role', 'type']);

    expect($response->json('role'))->toBe('customer');
    expect($response->json('access_token'))->toBeString()->not->toBeEmpty();
});

it('logs in an agent with owner context', function (): void {
    $user = createVerifiedUser('agent');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'owner',
    ]);

    $response->assertOk();
    expect($response->json('role'))->toBe('agent');

    // Verify token has correct prefix
    $token = $user->tokens()->first();
    expect($token->name)->toStartWith('owner_token_');
});

// ---------------------------------------------------------------------------
// Error paths
// ---------------------------------------------------------------------------

it('returns 401 for invalid credentials', function (): void {
    $user = createVerifiedUser('customer');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'WrongPassword!1',
    ]);

    $response->assertUnauthorized();
    expect($response->json('message'))->toBe('Identifiants invalides.');
});

it('returns 403 for inactive account', function (): void {
    $user = User::factory()->customers()->create([
        'password' => Hash::make('Secret@123'),
        'email_verified_at' => now(),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
    ]);

    $response->assertForbidden();
});

it('returns 403 for unverified email', function (): void {
    $user = User::factory()->customers()->unverified()->create([
        'password' => Hash::make('Secret@123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
    ]);

    $response->assertForbidden();
});

it('returns 403 for role context mismatch — customer as owner', function (): void {
    $user = createVerifiedUser('customer');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'owner',
    ]);

    $response->assertForbidden();
    expect($response->json('code'))->toBe('ROLE_CONTEXT_MISMATCH');
});

it('returns 403 for role context mismatch — agent as client', function (): void {
    $user = createVerifiedUser('agent');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'client',
    ]);

    $response->assertForbidden();
    expect($response->json('code'))->toBe('ROLE_CONTEXT_MISMATCH');
});

it('returns 429 when rate limited', function (): void {
    $user = createVerifiedUser('customer');

    $key = 'login-attempts:127.0.0.1|'.mb_strtolower($user->email);
    for ($i = 0; $i < 6; $i++) {
        RateLimiter::hit($key, 300);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
    ]);

    $response->assertTooManyRequests();
});

it('records login history after successful login', function (): void {
    $user = createVerifiedUser('customer');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
    ]);

    $this->assertDatabaseHas('login_histories', [
        'user_id' => $user->id,
        'successful' => true,
        'guard' => 'sanctum',
    ]);
});
