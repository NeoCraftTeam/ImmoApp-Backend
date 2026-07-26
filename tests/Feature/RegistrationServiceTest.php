<?php

declare(strict_types=1);

use App\DTOs\RegistrationResult;
use App\Exceptions\RegistrationEmailTakenException;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function validRegistrationData(array $overrides = []): array
{
    return array_merge([
        'firstname' => 'Jean',
        'lastname' => 'Dupont',
        'email' => 'jean'.uniqid().'@example.com',
        'phone_number' => '+237600000000',
        'password' => 'Secret@123',
        'confirm_password' => 'Secret@123',
        'role' => 'customer',
        'type' => 'individual',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// RegistrationService unit-ish tests (via HTTP to exercise full stack)
// ---------------------------------------------------------------------------

it('registers a customer successfully and returns 201', function (): void {
    $data = validRegistrationData();

    $response = $this->postJson('/api/v1/auth/registerCustomer', $data);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'user',
            'access_token',
            'email_verification_required',
        ]);

    expect($response->json('email_verification_required'))->toBeTrue();
    expect($response->json('access_token'))->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('users', [
        'email' => $data['email'],
        'role' => 'customer',
    ]);
});

it('sends the OTP verification code at registration (customer)', function (): void {
    Mail::fake();

    $data = validRegistrationData();

    $this->postJson('/api/v1/auth/registerCustomer', $data)->assertCreated();

    // L'OTP est déclenché à la CRÉATION du compte (via l'événement
    // Registered) — c'est le seul moment où un code est envoyé.
    Mail::assertQueued(VerificationCodeMail::class, fn ($m) => $m->hasTo($data['email']));
});

it('sends the OTP verification code at registration (agent)', function (): void {
    Mail::fake();

    $data = validRegistrationData(['role' => 'agent']);

    $this->postJson('/api/v1/auth/registerAgent', $data)->assertCreated();

    Mail::assertQueued(VerificationCodeMail::class, fn ($m) => $m->hasTo($data['email']));
});

it('registers an agent successfully and returns 201', function (): void {
    $data = validRegistrationData(['role' => 'agent']);

    $response = $this->postJson('/api/v1/auth/registerAgent', $data);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => $data['email'],
        'role' => 'agent',
    ]);
});

it('returns a generic 422 without leaking account existence or provider (OWASP)', function (): void {
    App::setLocale('fr');
    $generic = __('auth.registration_generic_conflict');

    // Trois états de compte différents doivent produire EXACTEMENT la
    // même réponse : aucun facteur de distinction (anti-énumération),
    // aucune divulgation du fournisseur, aucun code `registration_conflict`.
    $verified = User::factory()->customers()->create([
        'email_verified_at' => now(),
        'clerk_id' => null,
    ]);
    $clerk = User::factory()->customers()->create([
        'clerk_id' => 'user_'.uniqid(),
        'email_verified_at' => now(),
    ]);
    $unverified = User::factory()->customers()->unverified()->create([
        'clerk_id' => null,
    ]);

    foreach ([$verified, $clerk, $unverified] as $existing) {
        $response = $this->withHeaders(['Accept-Language' => 'fr'])->postJson(
            '/api/v1/auth/registerCustomer',
            validRegistrationData(['email' => $existing->email]),
        );

        $response->assertUnprocessable()
            ->assertJsonPath('errors.email.0', $generic)
            ->assertJsonMissingPath('registration_conflict');
    }

    // Le message générique ne doit pas révéler le fournisseur de connexion
    // (Google/SSO) ni affirmer qu'un compte existe (formulation conditionnelle
    // « Si vous avez déjà un compte » tolérée — OWASP password-recovery style).
    expect($generic)->not->toContain('Google')
        ->and($generic)->not->toContain('connexion sécurisée');
});

it('throws RegistrationEmailTakenException from service when email is taken', function (): void {
    $existing = User::factory()->customers()->create();
    $data = validRegistrationData(['email' => $existing->email]);
    $request = FormRequest::create('/api/v1/auth/registerCustomer', 'POST', $data);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->validateResolved();

    $service = app(RegistrationService::class);

    expect(fn () => $service->register($data, $request))
        ->toThrow(RegistrationEmailTakenException::class);
});

it('allows stateless (mobile) registration when turnstile is configured', function (): void {
    // Turnstile actif côté serveur, mais la requête est stateless (bearer
    // pur, sans session) comme l'app mobile Expo. Le widget Turnstile ne
    // peut pas tourner hors d'un navigateur, donc on ne doit PAS l'exiger.
    // Avant le fix, ceci renvoyait 422 {turnstile_token} et bloquait toute
    // inscription mobile en prod.
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    $data = validRegistrationData();

    $response = $this->postJson('/api/v1/auth/registerCustomer', $data);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => $data['email'],
        'role' => 'customer',
    ]);
});

it('allows mobile client registration without turnstile when turnstile is configured', function (): void {
    // Requête native mobile identifiée par `X-KeyHome-Client`. Même si un
    // domaine stateful attachait une session, le guard `isMobileAppRequest`
    // doit exempter le mobile de Turnstile (aucun widget navigateur possible).
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    $data = validRegistrationData();

    $response = $this->postJson('/api/v1/auth/registerCustomer', $data, [
        'X-KeyHome-Client' => 'keyhome-mobile-visitors',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => $data['email'],
        'role' => 'customer',
    ]);
});

it('returns 429 when rate limited', function (): void {
    // Exhaust rate limiter
    for ($i = 0; $i < 11; $i++) {
        RateLimiter::hit('register-attempts:127.0.0.1', 600);
    }

    $data = validRegistrationData();

    $response = $this->postJson('/api/v1/auth/registerCustomer', $data);

    $response->assertTooManyRequests();
});

it('returns token with correct prefix for customer', function (): void {
    $data = validRegistrationData();

    $response = $this->postJson('/api/v1/auth/registerCustomer', $data);

    $response->assertCreated();

    $user = User::where('email', $data['email'])->first();
    $token = $user->tokens()->first();

    expect($token->name)->toStartWith('client_registration_');
    expect($token->abilities)->toContain('role:customer', 'api:access');
});

it('returns token with correct prefix for agent', function (): void {
    $data = validRegistrationData(['role' => 'agent']);

    $response = $this->postJson('/api/v1/auth/registerAgent', $data);

    $response->assertCreated();

    $user = User::where('email', $data['email'])->first();
    $token = $user->tokens()->first();

    expect($token->name)->toStartWith('owner_registration_');
    expect($token->abilities)->toContain('role:agent', 'api:access');
});

it('service returns RegistrationResult DTO', function (): void {
    $service = app(RegistrationService::class);

    $data = validRegistrationData();
    $request = FormRequest::create('/api/v1/auth/registerCustomer', 'POST', $data);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->validateResolved();

    $result = $service->register($data, $request);

    expect($result)->toBeInstanceOf(RegistrationResult::class);
    expect($result->user)->toBeInstanceOf(User::class);
    expect($result->emailVerificationRequired)->toBeTrue();
    expect($result->token->plainTextToken)->toBeString()->not->toBeEmpty();
});
