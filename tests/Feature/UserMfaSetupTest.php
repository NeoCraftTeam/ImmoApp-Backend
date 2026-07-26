<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FAQRCode\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

// ---------------------------------------------------------------------------
// /auth/mfa/status — exposes mfa_configured for any user
// ---------------------------------------------------------------------------

it('exposes mfa_configured=false for a customer with no MFA', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $response = $this->getJson('/api/v1/auth/mfa/status');

    $response->assertOk()
        ->assertJson([
            'mfa_required' => false,
            'mfa_configured' => false,
            'methods' => [],
        ]);
});

it('exposes mfa_configured=true once TOTP is enrolled', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    $customer->saveAppAuthenticationSecret($google2fa->generateSecretKey(32));
    Sanctum::actingAs($customer);

    $response = $this->getJson('/api/v1/auth/mfa/status');

    $response->assertOk()
        ->assertJsonPath('mfa_configured', true)
        ->assertJsonPath('methods', ['totp']);
});

// ---------------------------------------------------------------------------
// /auth/mfa/setup/totp/start
// ---------------------------------------------------------------------------

it('issues a TOTP secret + otpauth url to a customer', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/start');

    $response->assertOk()
        ->assertJsonStructure(['secret', 'otpauth_url', 'holder', 'company', 'expires_in_minutes']);

    // Secret stays pending — never persisted yet.
    expect($customer->fresh()->getAppAuthenticationSecret())->toBeNull();
    expect(Cache::has('mfa_pending_totp:'.$customer->id))->toBeTrue();
});

it('rejects TOTP setup start when TOTP is already enabled', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    $customer->saveAppAuthenticationSecret($google2fa->generateSecretKey(32));
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/start');

    $response->assertStatus(422)
        ->assertJsonPath('code', 'MFA_TOTP_ALREADY_ENABLED');
});

// ---------------------------------------------------------------------------
// /auth/mfa/setup/totp/confirm
// ---------------------------------------------------------------------------

it('confirms TOTP setup, persists secret, returns recovery codes', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    // Start the flow to seed the pending secret.
    $start = $this->postJson('/api/v1/auth/mfa/setup/totp/start');
    $secret = $start->json('secret');

    $validCode = $google2fa->getCurrentOtp($secret);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/confirm', [
        'code' => $validCode,
    ]);

    $response->assertOk()
        ->assertJsonPath('mfa_method', 'totp')
        ->assertJsonStructure(['recovery_codes']);

    expect($response->json('recovery_codes'))->toHaveCount(8);
    expect($customer->fresh()->getAppAuthenticationSecret())->toBe($secret);
    expect($customer->fresh()->getAppAuthenticationRecoveryCodes())->toHaveCount(8);

    // Pending cache must be cleared once confirmed.
    expect(Cache::has('mfa_pending_totp:'.$customer->id))->toBeFalse();
});

// ---------------------------------------------------------------------------
// /auth/mfa/setup/totp/recovery-codes/regenerate
// ---------------------------------------------------------------------------

it('regenerates recovery codes with a valid TOTP code and discards the old set', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    $secret = $google2fa->generateSecretKey(32);
    $customer->saveAppAuthenticationSecret($secret);
    $customer->saveAppAuthenticationRecoveryCodes(['OLD1-OLD1', 'OLD2-OLD2']);
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/recovery-codes/regenerate', [
        'code' => $google2fa->getCurrentOtp($secret),
    ]);

    $response->assertOk()
        ->assertJsonPath('mfa_method', 'totp')
        ->assertJsonStructure(['recovery_codes']);

    expect($response->json('recovery_codes'))->toHaveCount(8);

    $fresh = $customer->fresh()->getAppAuthenticationRecoveryCodes();
    expect($fresh)->toHaveCount(8)
        ->and($fresh)->not->toContain('OLD1-OLD1');
});

it('rejects recovery-code regeneration with an invalid code (old codes preserved)', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    $customer->saveAppAuthenticationSecret($google2fa->generateSecretKey(32));
    $customer->saveAppAuthenticationRecoveryCodes(['OLD1-OLD1']);
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/recovery-codes/regenerate', [
        'code' => '000000',
    ]);

    $response->assertStatus(422)->assertJsonPath('code', 'MFA_INVALID_CODE');
    expect($customer->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['OLD1-OLD1']);
});

it('rejects recovery-code regeneration when TOTP is not enabled', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/auth/mfa/setup/totp/recovery-codes/regenerate', [
        'code' => '123456',
    ])->assertStatus(422)->assertJsonPath('code', 'MFA_TOTP_NOT_ENABLED');
});

it('rejects TOTP confirm with no active enrolment', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/confirm', [
        'code' => '123456',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'MFA_TOTP_NO_PENDING_SETUP');
});

it('rejects TOTP confirm with an invalid code', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/auth/mfa/setup/totp/start')->assertOk();

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/confirm', [
        'code' => '000000',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'MFA_TOTP_INVALID_CODE');
});

// ---------------------------------------------------------------------------
// /auth/mfa/setup/totp/disable
// ---------------------------------------------------------------------------

it('disables TOTP with a valid current code', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    $secret = $google2fa->generateSecretKey(32);
    $customer->saveAppAuthenticationSecret($secret);
    $customer->saveAppAuthenticationRecoveryCodes(['AAAA-AAAA']);
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/disable', [
        'code' => $google2fa->getCurrentOtp($secret),
    ]);

    $response->assertOk()
        ->assertJsonPath('disabled', true);

    expect($customer->fresh()->getAppAuthenticationSecret())->toBeNull();
    expect($customer->fresh()->getAppAuthenticationRecoveryCodes())->toBeNull();
});

it('disables TOTP with a recovery code (consuming it)', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    $customer->saveAppAuthenticationSecret($google2fa->generateSecretKey(32));
    $customer->saveAppAuthenticationRecoveryCodes(['ABCD-1234', 'EFGH-5678']);
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/disable', [
        'code' => 'ABCD-1234',
    ]);

    $response->assertOk()
        ->assertJsonPath('disabled', true);

    expect($customer->fresh()->getAppAuthenticationSecret())->toBeNull();
});

it('rejects TOTP disable with an invalid code', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create();
    $customer->saveAppAuthenticationSecret($google2fa->generateSecretKey(32));
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/totp/disable', [
        'code' => '000000',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'MFA_INVALID_CODE');
    expect($customer->fresh()->getAppAuthenticationSecret())->not->toBeNull();
});

// ---------------------------------------------------------------------------
// /auth/mfa/setup/email/*
// ---------------------------------------------------------------------------

it('queues an OTP when a customer enables email MFA', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);

    $response = $this->postJson('/api/v1/auth/mfa/setup/email/enable');

    $response->assertStatus(202)
        ->assertJsonPath('code_sent', true);

    expect(Cache::has('mfa_pending_email:'.$customer->id))->toBeTrue();
    expect($customer->fresh()->hasEmailAuthentication())->toBeFalse();
});

it('confirms email MFA when the cached OTP matches', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);
    Cache::put('mfa_pending_email:'.$customer->id, '123456', now()->addMinutes(10));

    $response = $this->postJson('/api/v1/auth/mfa/setup/email/confirm', [
        'code' => '123456',
    ]);

    $response->assertOk()
        ->assertJsonPath('mfa_method', 'email');

    expect($customer->fresh()->hasEmailAuthentication())->toBeTrue();
});

it('rejects email MFA confirm with the wrong code', function (): void {
    $customer = User::factory()->customers()->create();
    Sanctum::actingAs($customer);
    Cache::put('mfa_pending_email:'.$customer->id, '123456', now()->addMinutes(10));

    $response = $this->postJson('/api/v1/auth/mfa/setup/email/confirm', [
        'code' => '000000',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'MFA_EMAIL_INVALID_CODE');
});

it('disables email MFA via a two-step confirm flow', function (): void {
    $customer = User::factory()->customers()->create();
    $customer->toggleEmailAuthentication(true);
    Sanctum::actingAs($customer);

    // Step 1: no code → sends OTP, returns 202
    $first = $this->postJson('/api/v1/auth/mfa/setup/email/disable');
    $first->assertStatus(202)->assertJsonPath('code_sent', true);

    $otp = Cache::get('mfa_pending_email_disable:'.$customer->id);
    expect($otp)->toBeString();

    // Step 2: supply OTP → flag flipped off
    $second = $this->postJson('/api/v1/auth/mfa/setup/email/disable', [
        'code' => $otp,
    ]);

    $second->assertOk()->assertJsonPath('disabled', true);
    expect($customer->fresh()->hasEmailAuthentication())->toBeFalse();
});

// ---------------------------------------------------------------------------
// /auth/mfa/verify — now open to non-admin users
// ---------------------------------------------------------------------------

it('lets a customer verify TOTP via /auth/mfa/verify after login', function (): void {
    $google2fa = app(Google2FA::class);
    $customer = User::factory()->customers()->create(['password' => bcrypt('Password123@')]);
    $secret = $google2fa->generateSecretKey(32);
    $customer->saveAppAuthenticationSecret($secret);

    // Real Sanctum token (not actingAs) so currentAccessToken() returns a PAT.
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $customer->email,
        'password' => 'Password123@',
    ]);
    $token = $login->json('access_token');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/mfa/verify', [
            'method' => 'totp',
            'code' => $google2fa->getCurrentOtp($secret),
        ]);

    $response->assertOk()
        ->assertJsonPath('mfa_verified', true);
});

it('rejects /auth/mfa/verify when the user has no MFA configured', function (): void {
    $customer = User::factory()->customers()->create(['password' => bcrypt('Password123@')]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $customer->email,
        'password' => 'Password123@',
    ]);
    $token = $login->json('access_token');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/mfa/verify', [
            'method' => 'totp',
            'code' => '123456',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'MFA_NOT_CONFIGURED');
});
