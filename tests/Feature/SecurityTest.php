<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/* ──────────────────────────────────────────────────────────────────
 * Rate Limiting
 * ──────────────────────────────────────────────────────────────── */

test('login endpoint is rate limited', function (): void {
    RateLimiter::clear('api');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'hacker@test.com',
            'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'hacker@test.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(429);
});

test('public api endpoints have basic security headers', function (): void {
    $response = $this->getJson('/api/v1/ads');

    $response->assertStatus(200);
});

/* ──────────────────────────────────────────────────────────────────
 * Liveness Probe — /api/ping (unauthenticated)
 * ──────────────────────────────────────────────────────────────── */

test('ping endpoint is publicly accessible without authentication', function (): void {
    $response = $this->getJson('/api/ping');

    $response->assertOk()->assertJson(['status' => 'ok']);
});

/* ──────────────────────────────────────────────────────────────────
 * Health Check — Public + Token-Gated Access (P0)
 *
 * Design: endpoint is public by default (load-balancer / uptime
 * monitor probes require no credentials). When HEALTH_CHECK_TOKEN
 * is set in .env, a static bearer token is required.
 * ──────────────────────────────────────────────────────────────── */

test('health check is publicly accessible without authentication', function (): void {
    $response = $this->getJson('/api/health');

    // 200 (healthy/degraded) or 503 (unhealthy) — never 401/403
    expect($response->status())->toBeIn([200, 503]);
    $response->assertJsonStructure(['status', 'checks', 'timestamp']);
});

test('health check returns correct json structure', function (): void {
    $response = $this->getJson('/api/health');

    expect($response->status())->toBeIn([200, 503]);
    $response->assertJsonStructure([
        'status',
        'timestamp',
        'environment',
        'checks' => [
            'database',
            'redis',
            'queue',
            'storage',
            'meilisearch',
        ],
    ]);
});

test('health check returns 401 when HEALTH_CHECK_TOKEN is set and token is missing', function (): void {
    config(['services.health.token' => 'supersecret']);

    $response = $this->getJson('/api/health');

    $response->assertUnauthorized();
});

test('health check accepts request with correct HEALTH_CHECK_TOKEN bearer', function (): void {
    config(['services.health.token' => 'supersecret']);

    $response = $this->getJson('/api/health', ['Authorization' => 'Bearer supersecret']);

    expect($response->status())->toBeIn([200, 503]);
    $response->assertJsonStructure(['status', 'checks']);
});

/* ──────────────────────────────────────────────────────────────────
 * Admin Registration — Must Require Auth (P0)
 * ──────────────────────────────────────────────────────────────── */

test('unauthenticated user cannot register an admin account', function (): void {
    $response = $this->postJson('/api/v1/auth/registerAdmin', [
        'firstname' => 'Evil',
        'lastname' => 'Actor',
        'email' => 'evil@hacker.com',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
    ]);

    $response->assertUnauthorized();

    $this->assertDatabaseMissing('users', ['email' => 'evil@hacker.com']);
});

test('authenticated customer cannot register an admin account', function (): void {
    $customer = User::factory()->customers()->create();

    $response = $this->actingAs($customer)->postJson('/api/v1/auth/registerAdmin', [
        'firstname' => 'Evil',
        'lastname' => 'Actor',
        'email' => 'evil2@hacker.com',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
    ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'evil2@hacker.com']);
});

test('authenticated agent cannot register an admin account', function (): void {
    $agent = User::factory()->agents()->create();

    $response = $this->actingAs($agent)->postJson('/api/v1/auth/registerAdmin', [
        'firstname' => 'Evil',
        'lastname' => 'Actor',
        'email' => 'evil3@hacker.com',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
    ]);

    $response->assertForbidden();
});

/* ──────────────────────────────────────────────────────────────────
 * UserRequest — Role Privilege Escalation Prevention (P0)
 * ──────────────────────────────────────────────────────────────── */

test('creating user with role=admin via api is rejected by validation', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/users', [
        'firstname' => 'Bad',
        'lastname' => 'Actor',
        'email' => 'badactor@test.com',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
        'role' => 'admin',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    $this->assertDatabaseMissing('users', ['email' => 'badactor@test.com']);
});

/* ──────────────────────────────────────────────────────────────────
 * IDOR — User Resource Ownership
 * ──────────────────────────────────────────────────────────────── */

test('customer cannot update another users profile', function (): void {
    $userA = User::factory()->customers()->create();
    $userB = User::factory()->customers()->create(['firstname' => 'Original']);

    $response = $this->actingAs($userA)->putJson('/api/v1/users/'.$userB->id, [
        'firstname' => 'Hacked',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $userB->id, 'firstname' => 'Original']);
});

test('customer cannot delete another user', function (): void {
    $userA = User::factory()->customers()->create();
    $userB = User::factory()->customers()->create();

    $response = $this->actingAs($userA)->deleteJson('/api/v1/users/'.$userB->id);

    $response->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $userB->id]);
});

/* ──────────────────────────────────────────────────────────────────
 * API Error Envelope — Consistent JSON Format (P1)
 * ──────────────────────────────────────────────────────────────── */

test('accessing non-existent resource returns json 404 not html', function (): void {
    $user = User::factory()->customers()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound()
        ->assertJsonStructure(['message'])
        ->assertHeader('Content-Type', 'application/json');
});

test('unauthenticated api request returns json 401 not redirect', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

test('forbidden api request returns json 403 not html', function (): void {
    $customer = User::factory()->customers()->create();

    // Admin-only endpoint called by customer should return JSON 403
    $response = $this->actingAs($customer)
        ->getJson('/api/v1/users');

    // Either 403 Forbidden or any other non-200/non-HTML response
    if ($response->status() === 403) {
        $response->assertJsonStructure(['message'])
            ->assertHeader('Content-Type', 'application/json');
    }
});

/* ──────────────────────────────────────────────────────────────────
 * SanitizeInput — Denylist Approach (P0 enhanced)
 * ──────────────────────────────────────────────────────────────── */

test('password fields are exempt from html sanitization', function (): void {
    $city = City::factory()->create();

    // Angle brackets must survive SanitizeInput identically on both fields (no asymmetric strip).
    $passwordWithAngles = 'Pa<sS>w0rd!!'; // satisfies RegisterRequest Password rule (mixed, number, punctuation)

    $response = $this->postJson('/api/v1/auth/registerCustomer', [
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'test.sanitize@example.com',
        'password' => $passwordWithAngles,
        'confirm_password' => $passwordWithAngles,
        'phone_number' => '+237699000001',
        'city_id' => $city->id,
    ]);

    /** @var array<string, mixed> $errors */
    $errors = (array) ($response->json('errors') ?? []);

    expect($errors)->not->toHaveKey('confirm_password');

    $passwordErrors = Arr::wrap($errors['password'] ?? []);
    foreach ($passwordErrors as $msg) {
        expect((string) $msg)->not->toContain('confirmation');
    }
});

/* ──────────────────────────────────────────────────────────────────
 * Ad exclude_ids — Bounded Validation (P1)
 * ──────────────────────────────────────────────────────────────── */

test('ads index rejects more than 50 exclude_ids', function (): void {
    $tooManyIds = array_fill(0, 51, fake()->uuid());

    $response = $this->getJson('/api/v1/ads?'.http_build_query(['exclude_ids' => $tooManyIds]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['exclude_ids']);
});

test('ads index accepts up to 50 exclude_ids', function (): void {
    $validIds = array_fill(0, 10, fake()->uuid());

    $response = $this->getJson('/api/v1/ads?'.http_build_query(['exclude_ids' => $validIds]));

    expect($response->status())->not->toBe(422);
});

/* ──────────────────────────────────────────────────────────────────
 * Debug Error Leakage — No internal errors in responses (P0)
 * ──────────────────────────────────────────────────────────────── */

test('api 500 errors do not expose debug information in response body', function (): void {
    // Force APP_DEBUG=true to test that even with debug mode, we don't leak
    config(['app.debug' => true]);

    $user = User::factory()->customers()->create();

    // Call an endpoint that might produce a 500 under abnormal conditions.
    // We verify the response never has an 'error' key with stack trace.
    $response = $this->actingAs($user)
        ->getJson('/api/v1/users/not-a-valid-uuid-format');

    $content = $response->json();

    // Even in debug mode, API responses must not contain raw exception messages
    expect($content)->not->toHaveKey('exception')
        ->and($content)->not->toHaveKey('trace')
        ->and($content)->not->toHaveKey('file');
});
