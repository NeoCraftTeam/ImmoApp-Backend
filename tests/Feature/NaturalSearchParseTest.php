<?php

declare(strict_types=1);

use App\Models\AdType;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    AdType::factory()->create(['name' => 'Appartement']);
    AdType::factory()->create(['name' => 'Maison']);
    City::factory()->create(['name' => 'Douala']);
    City::factory()->create(['name' => 'Yaoundé']);
});

it('parses natural language query and returns structured search params', function (): void {
    $response = $this->postJson('/api/v1/search/parse', [
        'q' => 'appartement 3 pièces à Douala moins de 150 000 FCFA avec parking',
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    expect($data)->toHaveKeys([
        'original_query', 'type_id', 'type_name', 'city_id', 'city_name',
        'quarter_name', 'bedrooms', 'price_max', 'price_min', 'surface_min',
        'has_parking', 'furnished', 'q',
    ])
        ->and($data['type_name'])->toBe('Appartement')
        ->and($data['city_name'])->toBe('Douala')
        ->and($data['bedrooms'])->toBe(3)
        ->and($data['price_max'])->toBe(150000)
        ->and($data['has_parking'])->toBeTrue();
});

it('requires q parameter', function (): void {
    $response = $this->postJson('/api/v1/search/parse', []);

    $response->assertUnprocessable();
});

it('rejects q exceeding 300 characters', function (): void {
    $response = $this->postJson('/api/v1/search/parse', [
        'q' => str_repeat('a', 301),
    ]);

    $response->assertUnprocessable();
});

it('falls back to full-text q when no structured criteria found', function (): void {
    $response = $this->postJson('/api/v1/search/parse', [
        'q' => 'quelque chose de vague',
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['q'])->toBe('quelque chose de vague')
        ->and($data['type_id'])->toBeNull()
        ->and($data['city_id'])->toBeNull();
});

it('caches parsed results', function (): void {
    $response1 = $this->postJson('/api/v1/search/parse', [
        'q' => 'appartement à Douala',
    ]);
    $response2 = $this->postJson('/api/v1/search/parse', [
        'q' => 'appartement à Douala',
    ]);

    $response1->assertSuccessful();
    $response2->assertSuccessful();
    expect($response1->json())->toBe($response2->json());
});
