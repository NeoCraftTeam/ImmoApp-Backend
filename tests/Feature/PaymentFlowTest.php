<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('can get unlock price', function (): void {
    $response = $this->getJson('/api/v1/payments/unlock-price');

    $response->assertOk()
        ->assertJsonStructure(['unlock_price']);
    expect($response->json('unlock_price'))->toBeInt();
});

it('unauthenticated user cannot access unlocked ads', function (): void {
    $response = $this->getJson('/api/v1/my/unlocked-ads');

    $response->assertUnauthorized();
});

it('authenticated user can access their unlocked ads', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/my/unlocked-ads');

    $response->assertOk();
});

it('unlocked ads returns empty for new user', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/my/unlocked-ads');

    $response->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonCount(0, 'data');
});
