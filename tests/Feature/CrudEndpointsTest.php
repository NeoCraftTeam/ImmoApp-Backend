<?php

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('can list cities', function (): void {
    $response = $this->getJson('/api/v1/cities');

    $response->assertOk();
});

it('can show a city', function (): void {
    $city = City::factory()->create();

    $response = $this->getJson("/api/v1/cities/{$city->id}");

    $response->assertOk();
});

it('authenticated user can create a city', function (): void {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/cities', [
        'name' => 'Paris',
    ]);

    $response->assertSuccessful();
});

it('unauthenticated user cannot create a city', function (): void {
    $response = $this->postJson('/api/v1/cities', [
        'name' => 'Paris',
    ]);

    $response->assertUnauthorized();
});

it('can list quarters', function (): void {
    $response = $this->getJson('/api/v1/quarters');

    $response->assertOk();
});

it('can list agencies', function (): void {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/agencies');

    $response->assertOk();
});

it('authenticated user can list ad types', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/ad-types');

    $response->assertOk();
});

it('unauthenticated user cannot list ad types', function (): void {
    $response = $this->getJson('/api/v1/ad-types');

    $response->assertUnauthorized();
});

it('authenticated user can list users', function (): void {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/users');

    $response->assertOk();
});

it('unauthenticated user cannot list users', function (): void {
    $response = $this->getJson('/api/v1/users');

    $response->assertUnauthorized();
});
