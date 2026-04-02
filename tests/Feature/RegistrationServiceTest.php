<?php

declare(strict_types=1);

use App\DTOs\RegistrationResult;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('registers an agent successfully and returns 201', function (): void {
    $data = validRegistrationData(['role' => 'agent']);

    $response = $this->postJson('/api/v1/auth/registerAgent', $data);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => $data['email'],
        'role' => 'agent',
    ]);
});

it('returns 422 when email already exists', function (): void {
    $existing = User::factory()->customers()->create();
    $data = validRegistrationData(['email' => $existing->email]);

    $response = $this->postJson('/api/v1/auth/registerCustomer', $data);

    $response->assertUnprocessable();
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
