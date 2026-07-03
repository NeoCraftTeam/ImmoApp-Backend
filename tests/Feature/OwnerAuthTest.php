<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/* ──────────────────────────────────────────────────────────────────
 * Owner Login — login_context enforcement
 * ──────────────────────────────────────────────────────────────── */

test('agent can login with login_context=owner', function (): void {
    $user = User::factory()->agents()->create([
        'email' => 'agent@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'agent@test.com',
        'password' => 'password',
        'login_context' => 'owner',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'expires_at', 'role', 'type'])
        ->assertJsonPath('role', 'agent');
});

test('admin cannot login with login_context=owner', function (): void {
    User::factory()->admin()->create([
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@test.com',
        'password' => 'password',
        'login_context' => 'owner',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'PANEL_ACCESS_DENIED')
        ->assertJsonPath('message', 'Identifiants incorrects');
});

test('customer cannot login with login_context=owner', function (): void {
    User::factory()->customers()->create([
        'email' => 'client@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'client@test.com',
        'password' => 'password',
        'login_context' => 'owner',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'PANEL_ACCESS_DENIED');
});

test('agent cannot login with login_context=client', function (): void {
    User::factory()->agents()->create([
        'email' => 'agent@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'agent@test.com',
        'password' => 'password',
        'login_context' => 'client',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'PANEL_ACCESS_DENIED');
});

test('customer can login with login_context=client', function (): void {
    User::factory()->customers()->create([
        'email' => 'client@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'client@test.com',
        'password' => 'password',
        'login_context' => 'client',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'role'])
        ->assertJsonPath('role', 'customer');
});

test('login without login_context defaults to client', function (): void {
    User::factory()->customers()->create([
        'email' => 'client@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'client@test.com',
        'password' => 'password',
    ]);

    $response->assertOk();
});

/* ──────────────────────────────────────────────────────────────────
 * Session (Token) Isolation
 * ──────────────────────────────────────────────────────────────── */

test('dual session: owner login does not revoke client tokens', function (): void {
    $user = User::factory()->create([
        'email' => 'dual@test.com',
        'password' => bcrypt('password'),
        'role' => 'customer',
    ]);

    // Create a client token
    $clientToken = $user->createToken('client_token_100', ['role:customer', 'api:access'], now()->addDay());
    expect($user->tokens()->where('name', 'like', 'client_token_%')->count())->toBe(1);

    // Now change role to agent to simulate dual-role user
    $user->forceFill(['role' => 'agent', 'type' => 'individual'])->save();

    // Login as owner
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'dual@test.com',
        'password' => 'password',
        'login_context' => 'owner',
    ]);

    $response->assertOk();

    // Client token should still exist
    expect($user->tokens()->where('name', 'like', 'client_token_%')->count())->toBe(1);
    // Owner token should now exist
    expect($user->tokens()->where('name', 'like', 'owner_token_%')->count())->toBe(1);
});

/* ──────────────────────────────────────────────────────────────────
 * Owner Registration Flow
 * ──────────────────────────────────────────────────────────────── */

test('agent can register and receives role-scoped token', function (): void {
    Notification::fake();
    $city = City::factory()->create();

    $response = $this->postJson('/api/v1/auth/registerAgent', [
        'firstname' => 'Jane',
        'lastname' => 'Owner',
        'email' => 'jane.owner@test.com',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
        'phone_number' => '+237699990000',
        'type' => 'individual',
        'city_id' => $city->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['user', 'access_token', 'email_verification_required']);

    $user = User::where('email', 'jane.owner@test.com')->first();
    expect($user->role->value)->toBe('agent');

    // Verify token has role-scoped name
    $token = $user->tokens()->first();
    expect($token->name)->toStartWith('owner_registration_');
});

/* ──────────────────────────────────────────────────────────────────
 * OTP Verification — Role-Scoped Token
 * ──────────────────────────────────────────────────────────────── */

test('OTP verification creates role-scoped token for agent', function (): void {
    $user = User::factory()->agents()->unverified()->create([
        'email' => 'otp-agent@test.com',
    ]);

    // Place OTP in cache
    Cache::put('email_otp_'.$user->id, '123456', now()->addMinutes(10));

    $response = $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => 'otp-agent@test.com',
        'otp' => '123456',
    ]);

    $response->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('role', 'agent');

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();

    // Verify token naming
    $token = $user->tokens()->first();
    expect($token->name)->toStartWith('owner_auth_');
});

test('OTP verification creates role-scoped token for customer', function (): void {
    $user = User::factory()->customers()->unverified()->create([
        'email' => 'otp-client@test.com',
    ]);

    Cache::put('email_otp_'.$user->id, '654321', now()->addMinutes(10));

    $response = $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => 'otp-client@test.com',
        'otp' => '654321',
    ]);

    $response->assertOk()
        ->assertJsonPath('role', 'customer');

    $token = $user->tokens()->first();
    expect($token->name)->toStartWith('client_auth_');
});

/* ──────────────────────────────────────────────────────────────────
 * me() Endpoint
 * ──────────────────────────────────────────────────────────────── */

test('me returns 403 for unverified user', function (): void {
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('test', ['*'], now()->addDay());

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/auth/me');

    $response->assertForbidden()
        ->assertJsonPath('email_verification_required', true)
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('user_id', $user->id);
});

test('an unverified user can fix a mistyped email before verifying', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'typo@exemple.com']);
    $token = $user->createToken('test', ['*'], now()->addDay());

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/update-unverified-email', ['email' => 'correct@exemple.com']);

    $response->assertOk()->assertJsonPath('email', 'correct@exemple.com');
    expect($user->fresh()->email)->toBe('correct@exemple.com')
        ->and($user->fresh()->email_verified_at)->toBeNull();
});

test('a verified user cannot use the unverified-email fix endpoint', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['*'], now()->addDay());

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/update-unverified-email', ['email' => 'new@exemple.com'])
        ->assertForbidden();

    expect($user->fresh()->email)->not->toBe('new@exemple.com');
});

test('the unverified-email fix rejects an email already taken', function (): void {
    User::factory()->create(['email' => 'taken@exemple.com']);
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('test', ['*'], now()->addDay());

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/update-unverified-email', ['email' => 'taken@exemple.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('me returns role and type at top level', function (): void {
    $user = User::factory()->agents()->create();
    $token = $user->createToken('test', ['*'], now()->addDay());

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('role', 'agent')
        ->assertJsonStructure(['data', 'role', 'type']);
});

/* ──────────────────────────────────────────────────────────────────
 * EnsureTokenMatchesRole Middleware
 * ──────────────────────────────────────────────────────────────── */

test('token.role middleware rejects mismatched token ability', function (): void {
    $user = User::factory()->agents()->create();
    // Create a token with customer abilities (mismatch)
    $token = $user->createToken('client_token_test', ['role:customer', 'api:access'], now()->addDay());

    // Try to access an owner-protected route (ads store requires owner.role + token.role:agent)
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/ads', [
            'title' => 'Test',
        ]);

    $response->assertForbidden();
});

test('token.role middleware allows matching token ability', function (): void {
    $user = User::factory()->agents()->create();
    $token = $user->createToken('owner_token_test', ['role:agent', 'api:access'], now()->addDay());

    // The request will fail validation (missing fields) but should NOT fail on middleware
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/ads', []);

    // 422 = validation error, meaning middleware passed
    expect($response->status())->not->toBe(403);
});

test('legacy tokens with wildcard abilities pass token.role middleware', function (): void {
    $user = User::factory()->agents()->create();
    // Legacy token with ['*'] abilities
    $token = $user->createToken('legacy_token', ['*'], now()->addDay());

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/ads', []);

    // Should not be blocked by token.role middleware (wildcard passes)
    expect($response->status())->not->toBe(403);
});

/* ──────────────────────────────────────────────────────────────────
 * Edge Cases
 * ──────────────────────────────────────────────────────────────── */

test('unverified user cannot login', function (): void {
    User::factory()->unverified()->customers()->create([
        'email' => 'unverified@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'unverified@test.com',
        'password' => 'password',
    ]);

    $response->assertForbidden();
});

test('OTP verification fails with wrong code', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'wrong-otp@test.com',
    ]);

    Cache::put('email_otp_'.$user->id, '123456', now()->addMinutes(10));

    $response = $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => 'wrong-otp@test.com',
        'otp' => '999999',
    ]);

    $response->assertStatus(400);
});

test('OTP verification fails with expired code', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'expired-otp@test.com',
    ]);

    // Don't put OTP in cache (simulates expiration)

    $response = $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => 'expired-otp@test.com',
        'otp' => '123456',
    ]);

    $response->assertStatus(400);
});
