<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Regression coverage for the admin-panel A01/A05 hardening (audit 2026-05-10):
 *
 *   A1 — `registerAdmin` was declared in routes but missing from the
 *        controller; a real admin call would raise BadMethodCallException.
 *   A2 — Admin refunds (`POST /admin/payments/{p}/refund`) were gated by
 *        `can:admin-access` only — missing `mfa.admin`, unlike other admin
 *        writes (users create/destroy, registerAdmin, etc.).
 *   A4 — `PUT /users/{user}` was missing `mfa.admin` while POST/DELETE
 *        on the same collection enforce it.
 *   A5 — Telescope gate principal list was hard-coded to a single e-mail;
 *        now sourced from `TELESCOPE_ALLOWED_EMAILS` (fail-closed).
 */
it('A1: admin can now create another admin via registerAdmin (happy path)', function (): void {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/registerAdmin', [
            'firstname' => 'Newadmin',
            'lastname' => 'Bootstrap',
            'email' => 'new.admin@example.com',
            'phone_number' => '+237600000001',
            'password' => 'Password123@',
            'confirm_password' => 'Password123@',
        ]);

    expect($response->getStatusCode())->toBe(201);
    $this->assertDatabaseHas('users', [
        'email' => 'new.admin@example.com',
        'role' => UserRole::ADMIN->value,
    ]);
});

it('A1: registerAdmin forces role=admin even if payload tries something else', function (): void {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/registerAdmin', [
            'firstname' => 'Sneaky',
            'lastname' => 'Payload',
            'email' => 'sneaky.admin@example.com',
            'phone_number' => '+237600000002',
            'password' => 'Password123@',
            'confirm_password' => 'Password123@',
            'role' => 'customer', // ignored — controller hard-overrides.
        ]);

    expect($response->getStatusCode())->toBe(201);
    $this->assertDatabaseHas('users', [
        'email' => 'sneaky.admin@example.com',
        'role' => UserRole::ADMIN->value,
    ]);
});

it('A2: refund endpoint now requires MFA for admins with MFA enrolled', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
        'has_email_authentication' => true, // triggers mfa.admin enforcement
    ]);
    $payment = Payment::factory()->create();

    // Create a real PAT so `RequireApiMfa` doesn't bail on TransientToken.
    Cache::flush();
    $token = $admin->createToken('test', ['role:admin'])->plainTextToken;

    $response = $this->postJson(
        "/api/v1/admin/payments/{$payment->id}/refund",
        [],
        ['Authorization' => "Bearer {$token}"]
    );

    expect($response->getStatusCode())->toBe(403);
    expect($response->json('code'))->toBe('MFA_REQUIRED');
});

it('A4: PUT /users/{user} now requires MFA for admins with MFA enrolled', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::ADMIN,
        'has_email_authentication' => true,
    ]);
    $target = User::factory()->customers()->create();

    Cache::flush();
    $token = $admin->createToken('test', ['role:admin'])->plainTextToken;

    $response = $this->putJson(
        "/api/v1/users/{$target->id}",
        ['firstname' => 'Renamed'],
        ['Authorization' => "Bearer {$token}"]
    );

    expect($response->getStatusCode())->toBe(403);
    expect($response->json('code'))->toBe('MFA_REQUIRED');
});

it('A5: Telescope gate is fail-closed when TELESCOPE_ALLOWED_EMAILS is empty', function (): void {
    config()->set('app.env', 'production'); // gate only enforced outside local
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'admin@example.com']);

    config()->set('telescope.allowed_emails', '');

    // Reboot the gate so it re-reads config.
    $provider = new TelescopeServiceProvider(app());
    $reflection = new ReflectionMethod($provider, 'gate');
    $reflection->invoke($provider);

    expect(Gate::forUser($admin)->allows('viewTelescope'))->toBeFalse();
});

it('A5: Telescope gate allows admins in TELESCOPE_ALLOWED_EMAILS list', function (): void {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'email' => 'ops@example.com']);

    config()->set('telescope.allowed_emails', 'ops@example.com,cto@example.com');

    $provider = new TelescopeServiceProvider(app());
    $reflection = new ReflectionMethod($provider, 'gate');
    $reflection->invoke($provider);

    expect(Gate::forUser($admin)->allows('viewTelescope'))->toBeTrue();
});

it('A5: Telescope gate denies non-admin even if e-mail is in the allow list (defense in depth)', function (): void {
    $customer = User::factory()->customers()->create(['email' => 'customer@example.com']);

    config()->set('telescope.allowed_emails', 'customer@example.com');

    $provider = new TelescopeServiceProvider(app());
    $reflection = new ReflectionMethod($provider, 'gate');
    $reflection->invoke($provider);

    expect(Gate::forUser($customer)->allows('viewTelescope'))->toBeFalse();
});
