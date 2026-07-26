<?php

declare(strict_types=1);

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
    expect($response->json('message'))->toBe('Identifiants incorrects');
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

it('returns 403 for unverified email and signals the client to verify', function (): void {
    $user = User::factory()->customers()->unverified()->create([
        'password' => Hash::make('Secret@123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('email_verification_required', true)
        ->assertJsonPath('email', $user->email);
});

it('never re-sends an OTP code when an unverified account logs in', function (): void {
    Mail::fake();

    $user = User::factory()->customers()->unverified()->create([
        'password' => Hash::make('Secret@123'),
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
    ])->assertForbidden();

    // L'OTP appartient à l'inscription : se connecter ne doit JAMAIS
    // déclencher l'envoi d'un nouveau code de vérification.
    Mail::assertNotQueued(VerificationCodeMail::class);
});

it('returns 401 for role context mismatch — customer as owner', function (): void {
    $user = createVerifiedUser('customer');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'owner',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'PANEL_ACCESS_DENIED')
        ->assertJsonPath('message', 'Identifiants incorrects');
});

it('returns 401 for role context mismatch — agent as client', function (): void {
    $user = createVerifiedUser('agent');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'client',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'PANEL_ACCESS_DENIED');
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

it('allows mobile client login without turnstile when turnstile is configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    $user = createVerifiedUser('customer');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'client',
    ], [
        'X-KeyHome-Client' => 'keyhome-mobile-visitors',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token']);
});

it('allows mobile owner login without turnstile when turnstile is configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    $user = createVerifiedUser('agent');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Secret@123',
        'login_context' => 'owner',
    ], [
        'X-KeyHome-Client' => 'keyhome-mobile-owners',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token']);
});

it('accepts email login regardless of input casing', function (): void {
    $user = User::factory()->customers()->create([
        'email' => 'mixedcase@example.com',
        'password' => Hash::make('Secret@123'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'MixedCase@Example.COM',
        'password' => 'Secret@123',
        'login_context' => 'client',
    ], [
        'X-KeyHome-Client' => 'keyhome-mobile-visitors',
    ]);

    $response->assertOk();
    expect($response->json('access_token'))->toBeString()->not->toBeEmpty();
    expect($user->fresh()->id)->toBe($user->id);
});
