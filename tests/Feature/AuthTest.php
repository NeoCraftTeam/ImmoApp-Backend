<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;


use Illuminate\Support\Facades\Notification;

test('customer can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'role' => 'customer'
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password'
    ]);

    // On vérifie juste qu'on a un token
    $response->assertStatus(200)
        ->assertJsonStructure(['access_token']);
});

test('login fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password')
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password'
    ]);

    $response->assertStatus(401);
});

use App\Models\City;     // Ajout import

test('customer can register', function () {
    Notification::fake();
    $city = City::factory()->create();

    $response = $this->postJson('/api/v1/auth/registerCustomer', [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@new.com',
        'password' => 'Password123@',         // Password complexe
        'confirm_password' => 'Password123@', // Champ confirm_password
        'phone_number' => '+237699999999',
        'city_id' => $city->id
    ]);

    if ($response->status() !== 201) {
        dump($response->json());
    }

    $response->assertStatus(201)
        ->assertJsonStructure(['user', 'access_token']);

    $this->assertDatabaseHas('users', [
        'email' => 'john@new.com',
        'role' => 'customer'
    ]);
});
