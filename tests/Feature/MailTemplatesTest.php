<?php

declare(strict_types=1);

use App\Mail\EmailUpdatedMail;
use App\Mail\NewDeviceSignInMail;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── NewDeviceSignInMail ────────────────────────────────────────────────────────

it('queues NewDeviceSignInMail when user logs in from a new IP', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'last_login_ip' => '1.2.3.4',
    ]);

    // Login from a different IP to trigger the new device mail
    $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
        ->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

    Mail::assertQueued(NewDeviceSignInMail::class, fn ($m) => $m->hasTo($user->email));
});

it('does not queue NewDeviceSignInMail when logging in from the same IP', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'last_login_ip' => '127.0.0.1',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->assertOk();

    Mail::assertNotQueued(NewDeviceSignInMail::class);
});

// ── Token cleanup on login ────────────────────────────────────────────────────

it('revokes existing api_token_* tokens on new login', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    // Create two old tokens
    $user->createToken('api_token_111', ['*'], now()->addDays(7));
    $user->createToken('api_token_222', ['*'], now()->addDays(7));
    expect($user->tokens()->count())->toBe(2);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ])->assertOk();

    // After login, only 1 token should remain (the new one)
    expect($user->fresh()?->tokens()->count())->toBe(1);
});

// ── PasswordChangedMail ───────────────────────────────────────────────────────

it('queues PasswordChangedMail after a successful password reset', function (): void {
    Mail::fake();
    $user = User::factory()->create(['password' => bcrypt('OldPass123!')]);
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPass456!',
        'password_confirmation' => 'NewPass456!',
    ])->assertOk();

    Mail::assertQueued(PasswordChangedMail::class, fn ($m) => $m->hasTo($user->email));
});

// ── EmailUpdatedMail ──────────────────────────────────────────────────────────

it('queues EmailUpdatedMail to old address when user updates their email', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'old@keyhome.test']);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/users/{$user->id}", [
        'email' => 'new@keyhome.test',
    ])->assertOk();

    Mail::assertQueued(EmailUpdatedMail::class, fn ($m) => $m->hasTo('old@keyhome.test'));
});
