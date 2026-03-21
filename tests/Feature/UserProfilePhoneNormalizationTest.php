<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('normalizes phone_number with spaces on user self-update', function (): void {
    $user = User::factory()->create(['phone_number' => '+237600000000']);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/users/{$user->id}", [
        'phone_number' => '+237 600 000 001',
    ])->assertOk();

    expect($user->fresh()->phone_number)->toBe('+237600000001');
});

it('accepts phone_is_whatsapp on user update', function (): void {
    $user = User::factory()->create(['phone_is_whatsapp' => false]);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/users/{$user->id}", [
        'phone_is_whatsapp' => true,
    ])->assertOk();

    expect($user->fresh()->phone_is_whatsapp)->toBeTrue();
});

it('drops incomplete phone_number on update so city and other fields still save', function (): void {
    $cityA = City::factory()->create();
    $cityB = City::factory()->create();
    $user = User::factory()->create([
        'phone_number' => '+237611223344',
        'city_id' => $cityA->id,
    ]);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/users/{$user->id}", [
        'phone_number' => '+237',
        'city_id' => $cityB->id,
    ])->assertOk();

    $fresh = $user->fresh();
    expect($fresh->city_id)->toBe($cityB->id);
    expect($fresh->phone_number)->toBe('+237611223344');
});
