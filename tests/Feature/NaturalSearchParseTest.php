<?php

declare(strict_types=1);

use App\Models\AdType;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.ai_search.providers' => '',
        'services.groq.api_key' => '',
        'services.openai.api_key' => '',
        'services.gemini.api_key' => '',
        'services.together.api_key' => '',
        'services.mistral.api_key' => '',
    ]);
    Cache::flush();

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

it('parses "milles francs" price and accent-free city name', function (): void {
    $response = $this->postJson('/api/v1/search/parse', [
        'q' => 'je recherche une maison a moins de 50 milles francs dans la ville de yaounde',
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['type_name'])->toBe('Maison')
        ->and($data['city_name'])->toBe('Yaoundé')
        ->and($data['price_max'])->toBe(50000);
});

it('parses abbreviated "50k fcfa" price', function (): void {
    $response = $this->postJson('/api/v1/search/parse', [
        'q' => 'maison à Douala moins de 80k fcfa',
    ]);

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['type_name'])->toBe('Maison')
        ->and($data['city_name'])->toBe('Douala')
        ->and($data['price_max'])->toBe(80000);
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

it('uses same cache for canonically equivalent queries', function (): void {
    // These queries should hit the same cache entry after canonicalization:
    // "Appartement à Douala" → "appartement douala"
    // "douala appartement" → "appartement douala"
    // "appartement   de  Douala" → "appartement douala"

    $response1 = $this->postJson('/api/v1/search/parse', [
        'q' => 'Appartement à Douala',
    ]);

    $response2 = $this->postJson('/api/v1/search/parse', [
        'q' => 'douala appartement',
    ]);

    $response3 = $this->postJson('/api/v1/search/parse', [
        'q' => 'appartement   de  Douala',
    ]);

    $response1->assertSuccessful();
    $response2->assertSuccessful();
    $response3->assertSuccessful();

    // All three should return identical structured results
    expect($response1->json('type_name'))->toBe('Appartement')
        ->and($response1->json('city_name'))->toBe('Douala')
        ->and($response2->json('type_name'))->toBe('Appartement')
        ->and($response2->json('city_name'))->toBe('Douala')
        ->and($response3->json('type_name'))->toBe('Appartement')
        ->and($response3->json('city_name'))->toBe('Douala');
});
