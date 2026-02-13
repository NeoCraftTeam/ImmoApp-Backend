<?php

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('authenticated user can logout', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Password123@')]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertOk();
});

it('unauthenticated user cannot logout', function (): void {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized();
});

it('authenticated user can get their profile', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Password123@')]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'firstname', 'lastname', 'email']]);
});

it('unauthenticated user cannot get profile', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

it('authenticated user can refresh token', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Password123@')]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123@',
    ]);
    $token = $loginResponse->json('access_token');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/refresh');

    $response->assertOk()
        ->assertJsonStructure(['access_token']);
});

it('admin can register an agent', function (): void {
    $admin = User::factory()->admin()->create(['password' => bcrypt('Password123@')]);
    Sanctum::actingAs($admin);
    $city = City::factory()->create();

    $response = $this->postJson('/api/v1/auth/registerAgent', [
        'firstname' => 'Agent',
        'lastname' => 'User',
        'email' => 'agent@example.com',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
        'phone_number' => '+237699999999',
        'city_id' => $city->id,
        'type' => 'individual',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('users', [
        'email' => 'agent@example.com',
        'role' => 'agent',
    ]);
});

it('register agent is a public route and succeeds with valid data', function (): void {
    $city = City::factory()->create();

    $response = $this->postJson('/api/v1/auth/registerAgent', [
        'firstname' => 'Agent',
        'lastname' => 'User',
        'email' => 'agent@example.com',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
        'phone_number' => '+237699999999',
        'city_id' => $city->id,
        'type' => 'individual',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('users', [
        'email' => 'agent@example.com',
        'role' => 'agent',
    ]);
});

it('authenticated user can update password', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Password123@')]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123@',
    ]);
    $token = $loginResponse->json('access_token');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/update-password', [
            'current_password' => 'Password123@',
            'new_password' => 'NewPassword456@',
            'new_password_confirmation' => 'NewPassword456@',
        ]);

    $response->assertOk();
    $user->refresh();
    expect(Hash::check('NewPassword456@', $user->password))->toBeTrue();
});

it('update password fails with wrong current password', function (): void {
    $user = User::factory()->create(['password' => bcrypt('Password123@')]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/update-password', [
        'current_password' => 'WrongPassword123@',
        'new_password' => 'NewPassword456@',
        'new_password_confirmation' => 'NewPassword456@',
    ]);

    $response->assertUnprocessable();
});

it('login validation fails with missing fields', function (): void {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable();
});
