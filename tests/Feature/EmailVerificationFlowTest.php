<?php

declare(strict_types=1);

use App\Mail\VerifyEmailMail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

// ── HMAC-SHA256 email verification link ─────────────────────────────────────────────

it('verifies email with a valid HMAC-SHA256 signed URL', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'api.verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => hash_hmac('sha256', $user->email, (string) config('app.key')),
        ]
    );

    $this->getJson($url)
        ->assertOk()
        ->assertJson(['verified' => true]);

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

it('rejects verification with a tampered hash', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'api.verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => 'this-is-a-tampered-hash',
        ]
    );

    $this->getJson($url)->assertStatus(400);
    expect($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired verification link', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'api.verification.verify',
        now()->subMinute(),
        [
            'id' => $user->id,
            'hash' => hash_hmac('sha256', $user->email, (string) config('app.key')),
        ]
    );

    $this->getJson($url)->assertStatus(400);
    expect($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('returns 200 when email is already verified', function (): void {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute(
        'api.verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => hash_hmac('sha256', $user->email, (string) config('app.key')),
        ]
    );

    $this->getJson($url)->assertOk()->assertJson(['verified' => true]);
});

// ── Anti-énumération ──────────────────────────────────────────────────────────────────

it('resendVerificationEmail always returns 200 for unknown email', function (): void {
    $this->postJson('/api/v1/auth/resend-verification', ['email' => 'ghost@nonexistent.com'])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Si cette adresse est enregistrée et non vérifiée, un email a été envoyé.']);
});

it('resendVerificationEmail returns 200 for already-verified users', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Si cette adresse est enregistrée et non vérifiée, un email a été envoyé.']);
});

it('resendVerificationEmail queues VerifyEmailMail for unverified users', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])
        ->assertOk();

    Mail::assertQueued(VerifyEmailMail::class, fn ($m) => $m->hasTo($user->email));
});

it('forgotPassword always returns 200 for unknown email', function (): void {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@ghost.com'])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Si cette adresse est enregistrée, un email de réinitialisation a été envoyé.']);
});

// ── Welcome email déclenché par Verified event ─────────────────────────────────────────

it('queues WelcomeEmail when the Verified event fires', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    event(new Verified($user));

    Mail::assertQueued(WelcomeEmail::class, fn ($m) => $m->hasTo($user->email));
});

// ── sendEmailVerificationNotification utilise VerifyEmailMail ─────────────────────────

it('sendEmailVerificationNotification queues VerifyEmailMail', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Mail::assertQueued(VerifyEmailMail::class, fn ($m) => $m->hasTo($user->email));
});

// ── CreateAdminCommand ────────────────────────────────────────────────────────────────

it('create-admin command creates an unverified admin and sends VerifyEmailMail', function (): void {
    Mail::fake();

    $this->artisan('app:create-admin', [
        '--email' => 'newadmin@keyhome.test',
        '--firstname' => 'Super',
        '--lastname' => 'Admin',
        '--password' => 'Adm1nPass!',
    ])->assertSuccessful();

    $user = User::where('email', 'newadmin@keyhome.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->role->value)->toBe('admin');

    Mail::assertQueued(VerifyEmailMail::class, fn ($m) => $m->hasTo('newadmin@keyhome.test'));
});

it('create-admin command promotes existing user without sending verification', function (): void {
    Mail::fake();
    $user = User::factory()->create(['role' => \App\Enums\UserRole::CUSTOMER]);

    $this->artisan('app:create-admin', ['--email' => $user->email])
        ->expectsConfirmation('User '.$user->email.' exists as customer. Promote to admin?', 'yes')
        ->assertSuccessful();

    expect($user->fresh()?->role->value)->toBe('admin');
    Mail::assertNothingQueued();
});
