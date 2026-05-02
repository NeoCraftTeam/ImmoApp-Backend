<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('customer can login with valid credentials', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'role' => 'customer',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    // On vérifie juste qu'on a un token
    $response->assertStatus(200)
        ->assertJsonStructure(['access_token']);
});

test('login fails with invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

use App\Models\City;
use Illuminate\Support\Facades\Hash;

// Ajout import

test('customer can register', function (): void {
    Notification::fake();
    $city = City::factory()->create();

    $response = $this->postJson('/api/v1/auth/registerCustomer', [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@new.com',
        'password' => 'Password123@',         // Password complexe
        'confirm_password' => 'Password123@', // Champ confirm_password
        'phone_number' => '+237699999999',
        'city_id' => $city->id,
    ]);

    if ($response->status() !== 201) {
        dump($response->json());
    }

    $response->assertStatus(201)
        ->assertJsonStructure(['user', 'access_token']);

    $this->assertDatabaseHas('users', [
        'email' => 'john@new.com',
        'role' => 'customer',
    ]);

    // Regression guard: the password must actually be hashed and stored, so
    // the freshly-registered user can authenticate. This catches accidental
    // removal of `password` from `User::$fillable` (which silently drops the
    // value via `fill()` and creates a NULL-password account).
    $created = User::where('email', 'john@new.com')->firstOrFail();
    expect($created->password)->not->toBeNull();
    expect(Hash::check('Password123@', $created->password))->toBeTrue();
});
