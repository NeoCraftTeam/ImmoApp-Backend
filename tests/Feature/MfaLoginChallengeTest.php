<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Services\Auth\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FAQRCode\Google2FA;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Second factor at login
|--------------------------------------------------------------------------
| A login whose owner enabled 2FA gets NO token: it gets a 403
| `MFA_CHALLENGE_REQUIRED` carrying a single-use `mfa_token`, and finishes at
| POST /auth/mfa/challenge. Every surface that mints a Sanctum token is gated:
| password, OAuth (Socialite) and Clerk. WebAuthn is exempt on purpose — a
| passkey already *is* a second factor.
*/

beforeEach(function (): void {
    Mail::fake();
});

/**
 * @return array{0: User, 1: string} the enrolled user and its TOTP secret
 */
function enrolTotpUser(array $attributes = []): array
{
    $user = User::factory()->customers()->create([
        'password' => bcrypt('Password123@'),
        ...$attributes,
    ]);

    $secret = app(Google2FA::class)->generateSecretKey(32);
    $user->saveAppAuthenticationSecret($secret);

    return [$user, $secret];
}

function currentTotp(string $secret): string
{
    return (string) app(Google2FA::class)->getCurrentOtp($secret);
}

function loginFor(User $user, array $payload = []): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123@',
        ...$payload,
    ]);
}

// ---------------------------------------------------------------------------
// Password login
// ---------------------------------------------------------------------------

it('answers a password login with a challenge instead of a token when TOTP is enabled', function (): void {
    [$user] = enrolTotpUser();

    $response = loginFor($user);

    $response->assertStatus(403)
        ->assertJsonPath('mfa_required', true)
        ->assertJsonPath('code', 'MFA_CHALLENGE_REQUIRED')
        ->assertJsonPath('methods', ['totp'])
        ->assertJsonPath('has_recovery_codes', false)
        ->assertJsonPath('attempts_remaining', 5)
        ->assertJsonStructure(['mfa_token', 'masked_email', 'expires_in_minutes']);

    expect($response->json('access_token'))->toBeNull()
        ->and($response->json('mfa_token'))->toHaveLength(64)
        // The full address must never come back on a pre-auth response.
        ->and($response->json('masked_email'))->not->toBe($user->email);

    // No token, no session, no login journal until the second factor passes.
    expect($user->tokens()->count())->toBe(0);
    $this->assertDatabaseCount('login_histories', 0);
    expect($user->fresh()->last_login_at)->toBeNull();
});

it('issues a working token once a valid TOTP code completes the challenge', function (): void {
    [$user, $secret] = enrolTotpUser();

    $challenge = loginFor($user)->json('mfa_token');

    $response = $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => currentTotp($secret),
    ]);

    $response->assertOk()
        ->assertJsonPath('mfa_method', 'totp')
        ->assertJsonPath('role', UserRole::CUSTOMER->value)
        ->assertJsonPath('is_new_user', false)
        ->assertJsonStructure(['access_token', 'token', 'expires_at', 'user']);

    // `token` is an alias of `access_token` for the mobile/OAuth clients.
    expect($response->json('token'))->toBe($response->json('access_token'));

    // The login journal now runs exactly as it would without a second factor.
    $this->assertDatabaseCount('login_histories', 1);
    expect($user->fresh()->last_login_at)->not->toBeNull();

    $this->withHeader('Authorization', 'Bearer '.$response->json('access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('marks the freshly issued token as MFA-verified so the user is not asked twice', function (): void {
    [$user, $secret] = enrolTotpUser();

    $challenge = loginFor($user)->json('mfa_token');

    $token = $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => currentTotp($secret),
    ])->json('access_token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/mfa/status')
        ->assertOk()
        ->assertJsonPath('mfa_verified', true);
});

it('lets a challenged login stay in the client panel context', function (): void {
    $user = User::factory()->agents()->create(['password' => bcrypt('Password123@')]);
    $secret = app(Google2FA::class)->generateSecretKey(32);
    $user->saveAppAuthenticationSecret($secret);

    $challenge = loginFor($user, ['login_context' => 'owner'])->json('mfa_token');

    $token = $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => currentTotp($secret),
    ])->assertOk()->json('access_token');

    // The owner prefix decided at step 1 survives the second factor.
    expect($user->fresh()->tokens()->whereNull('revoked_at')->first()?->name)->toStartWith('owner_token_');
    expect($token)->toBeString();
});

it('re-checks panel access when the challenge is completed', function (): void {
    $user = User::factory()->agents()->create(['password' => bcrypt('Password123@')]);
    $secret = app(Google2FA::class)->generateSecretKey(32);
    $user->saveAppAuthenticationSecret($secret);

    $challenge = loginFor($user, ['login_context' => 'owner'])->json('mfa_token');

    // Access revoked between the two steps: the ticket must not honour it.
    $user->forceFill(['role' => UserRole::CUSTOMER, 'type' => null])->save();

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => currentTotp($secret),
    ])
        ->assertStatus(401)
        ->assertJsonPath('code', 'PANEL_ACCESS_DENIED');
});

it('does not challenge an account without any second factor', function (): void {
    $user = User::factory()->customers()->create(['password' => bcrypt('Password123@')]);

    loginFor($user)
        ->assertOk()
        ->assertJsonStructure(['access_token', 'expires_at', 'role']);
});

// ---------------------------------------------------------------------------
// Wrong codes, expiry, replay
// ---------------------------------------------------------------------------

it('rejects a wrong code and counts the attempt down', function (): void {
    [$user] = enrolTotpUser();

    $challenge = loginFor($user)->json('mfa_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => '000000',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_INVALID_CODE')
        ->assertJsonPath('attempts_remaining', 4);

    expect($user->tokens()->count())->toBe(0);
});

it('destroys the challenge after five wrong codes and keeps spending the login budget', function (): void {
    [$user, $secret] = enrolTotpUser();

    $challenge = loginFor($user)->json('mfa_token');

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/mfa/challenge', [
            'mfa_token' => $challenge,
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'MFA_INVALID_CODE')
            ->assertJsonPath('attempts_remaining', 5 - $attempt);
    }

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => '000000',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_CHALLENGE_EXHAUSTED')
        ->assertJsonPath('attempts_remaining', 0);

    // The ticket is gone for good — even the right code cannot revive it.
    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => currentTotp($secret),
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_CHALLENGE_INVALID');

    // …and guessing codes spent the *login* budget, so starting over is barred
    // too: minting a fresh challenge cannot be used to reset the counter.
    loginFor($user)
        ->assertStatus(429)
        ->assertJsonPath('code', 'RATE_LIMITED');

    expect($user->tokens()->count())->toBe(0);
});

it('rejects an unknown challenge token', function (): void {
    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => str_repeat('z', 64),
        'code' => '123456',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_CHALLENGE_INVALID');
});

it('rejects a challenge whose account was deactivated in between', function (): void {
    [$user, $secret] = enrolTotpUser();

    $challenge = loginFor($user)->json('mfa_token');

    $user->forceFill(['is_active' => false])->save();

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => currentTotp($secret),
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_CHALLENGE_INVALID');
});

it('refuses to replay a TOTP code that already completed a login', function (): void {
    [$user, $secret] = enrolTotpUser();

    $code = currentTotp($secret);

    $first = loginFor($user)->json('mfa_token');
    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $first,
        'code' => $code,
    ])->assertOk();

    // Same code, brand-new challenge: the timestep is already spent.
    $second = loginFor($user)->json('mfa_token');
    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $second,
        'code' => $code,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_INVALID_CODE');
});

// ---------------------------------------------------------------------------
// Recovery codes
// ---------------------------------------------------------------------------

it('accepts a recovery code once, then never again', function (): void {
    [$user] = enrolTotpUser();
    $mfa = app(MfaService::class);
    $codes = $mfa->generateRecoveryCodes();
    $mfa->saveRecoveryCodes($user, $codes);

    // Stored hashed — a cache/DB dump does not hand over the codes.
    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->not->toContain($codes[0]);

    $challenge = loginFor($user)->assertJsonPath('has_recovery_codes', true)->json('mfa_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'method' => 'recovery',
        'code' => $codes[0],
    ])
        ->assertOk()
        ->assertJsonPath('mfa_method', 'recovery')
        ->assertJsonPath('recovery_codes_remaining', 7);

    // Completing a challenge clears the login budget, so a second login is free.
    $again = loginFor($user)->json('mfa_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $again,
        'method' => 'recovery',
        'code' => $codes[0],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_INVALID_CODE');
});

it('still accepts a legacy plaintext recovery code', function (): void {
    [$user] = enrolTotpUser();
    $user->saveAppAuthenticationRecoveryCodes(['LEGACY-CODE']);

    $challenge = loginFor($user)->json('mfa_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => 'LEGACY-CODE',
    ])
        ->assertOk()
        ->assertJsonPath('mfa_method', 'recovery')
        ->assertJsonPath('recovery_codes_remaining', 0);
});

// ---------------------------------------------------------------------------
// Email second factor
// ---------------------------------------------------------------------------

it('mails a code and completes the login for an email-only second factor', function (): void {
    $user = User::factory()->customers()->create(['password' => bcrypt('Password123@')]);
    $user->toggleEmailAuthentication(true);

    $challenge = loginFor($user)
        ->assertStatus(403)
        ->assertJsonPath('methods', ['email'])
        ->json('mfa_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'method' => 'email',
    ])
        ->assertStatus(202)
        ->assertJsonPath('code_sent', true);

    Mail::assertQueued(VerificationCodeMail::class);

    $otp = Cache::get('mfa_challenge_otp:'.hash('sha256', (string) $challenge));
    expect($otp)->toBeString();

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'method' => 'email',
        'code' => $otp,
    ])
        ->assertOk()
        ->assertJsonPath('mfa_method', 'email')
        ->assertJsonStructure(['access_token']);
});

it('refuses an email code for an account that never enabled email MFA', function (): void {
    [$user] = enrolTotpUser();

    $challenge = loginFor($user)->json('mfa_token');

    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'method' => 'email',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MFA_EMAIL_NOT_ENABLED');

    Mail::assertNothingQueued();
});

it('throttles a second email code request', function (): void {
    $user = User::factory()->customers()->create(['password' => bcrypt('Password123@')]);
    $user->toggleEmailAuthentication(true);

    $challenge = loginFor($user)->json('mfa_token');

    $send = fn () => $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'method' => 'email',
    ]);

    $send()->assertStatus(202);
    $send()->assertStatus(429)->assertJsonStructure(['retry_after']);

    Mail::assertQueued(VerificationCodeMail::class, 1);
});

// ---------------------------------------------------------------------------
// OAuth / Socialite
// ---------------------------------------------------------------------------

it('parks a native OAuth login behind the challenge', function (): void {
    $user = User::factory()->customers()->create(['google_id' => 'google-mfa-1']);
    $user->saveAppAuthenticationSecret(app(Google2FA::class)->generateSecretKey(32));

    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('google-mfa-1');
    $socialUser->shouldReceive('getEmail')->andReturn($user->email);
    $socialUser->shouldReceive('getName')->andReturn('MFA User');
    $socialUser->shouldReceive('getAvatar')->andReturn(null);
    $socialUser->shouldReceive('getRaw')->andReturn([]);

    // The native flow resolves the identity through `userFromToken`, not the
    // stateless `->user()` seam the web callback uses.
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('userFromToken')->andReturn($socialUser);

    $this->postJson('/api/v1/auth/oauth/google', ['token' => 'provider-token'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'MFA_CHALLENGE_REQUIRED')
        ->assertJsonStructure(['mfa_token']);

    expect($user->tokens()->count())->toBe(0);
});

it('returns the challenge when the web OAuth exchange code is redeemed', function (): void {
    $user = User::factory()->customers()->create(['google_id' => 'google-mfa-2']);
    $secret = app(Google2FA::class)->generateSecretKey(32);
    $user->saveAppAuthenticationSecret($secret);

    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('google-mfa-2');
    $socialUser->shouldReceive('getEmail')->andReturn($user->email);
    $socialUser->shouldReceive('getName')->andReturn('MFA User');
    $socialUser->shouldReceive('getAvatar')->andReturn(null);
    $socialUser->shouldReceive('getRaw')->andReturn([]);

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('stateless')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialUser);

    $frontend = (string) config('app.frontend_url');
    $state = base64_encode((string) json_encode(['redirect_uri' => $frontend.'/auth/callback']));

    $location = (string) $this->get('/api/v1/auth/oauth/google/callback?code=abc&state='.urlencode($state))
        ->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $challenge = $this->getJson('/api/v1/auth/oauth/exchange-token?exchange_code='.$query['exchange_code'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'MFA_CHALLENGE_REQUIRED')
        ->json('mfa_token');

    // …and the challenge finishes the OAuth login just like a password one.
    $this->postJson('/api/v1/auth/mfa/challenge', [
        'mfa_token' => $challenge,
        'code' => currentTotp($secret),
    ])
        ->assertOk()
        ->assertJsonStructure(['access_token', 'token'])
        ->assertJsonPath('mfa_method', 'totp');

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
