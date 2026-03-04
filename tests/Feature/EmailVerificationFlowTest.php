<?php

declare(strict_types=1);

use App\Mail\VerificationCodeMail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

it('resendVerificationEmail queues VerificationCodeMail for unverified users', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    $this->postJson('/api/v1/auth/resend-verification', ['email' => $user->email])
        ->assertOk();

    Mail::assertQueued(VerificationCodeMail::class, fn ($m) => $m->hasTo($user->email));
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

it('sendEmailVerificationNotification queues VerificationCodeMail with OTP', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Mail::assertQueued(VerificationCodeMail::class, fn ($m) => $m->hasTo($user->email));
    expect(Cache::has('email_otp_'.$user->id))->toBeTrue();
});

// ── CreateAdminCommand ────────────────────────────────────────────────────────────────

it('create-admin command creates an unverified admin and sends VerificationCodeMail', function (): void {
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

    Mail::assertQueued(VerificationCodeMail::class, fn ($m) => $m->hasTo('newadmin@keyhome.test'));
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

// ── OTP Email Verification ─────────────────────────────────────────────────────────────

it('verifies email with a valid OTP code', function (): void {
    $user = User::factory()->unverified()->create();
    Cache::put('email_otp_'.$user->id, '123456', now()->addMinutes(10));

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => '123456',
    ])
        ->assertOk()
        ->assertJsonStructure(['message', 'verified', 'access_token', 'user']);

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
    expect(Cache::has('email_otp_'.$user->id))->toBeFalse();
});

it('rejects an invalid OTP code', function (): void {
    $user = User::factory()->unverified()->create();
    Cache::put('email_otp_'.$user->id, '123456', now()->addMinutes(10));

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => '999999',
    ])->assertStatus(400);

    expect($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired OTP code', function (): void {
    $user = User::factory()->unverified()->create();

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => '123456',
    ])->assertStatus(400);

    expect($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('returns success for already-verified email via OTP', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => '000000',
    ])->assertOk()->assertJson(['verified' => true]);
});

it('returns access_token after successful OTP verification for auto-login', function (): void {
    $user = User::factory()->unverified()->create();
    Cache::put('email_otp_'.$user->id, '555555', now()->addMinutes(10));

    $response = $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => '555555',
    ])->assertOk();

    expect($response->json('access_token'))->not->toBeEmpty();
    expect($response->json('user'))->not->toBeEmpty();
});
