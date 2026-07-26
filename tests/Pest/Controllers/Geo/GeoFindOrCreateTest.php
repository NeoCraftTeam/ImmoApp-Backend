<?php

declare(strict_types=1);

use App\Actions\Geo\FindOrCreateCityAction;
use App\Actions\Geo\FindOrCreateQuarterAction;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

// ─── FindOrCreateCityAction ───────────────────────────────────────────────────

it('returns existing city without nominatim call', function (): void {
    $city = City::factory()->create(['name' => 'Douala', 'country' => 'Cameroun']);

    Http::preventStrayRequests();

    $result = (new FindOrCreateCityAction)->handle(['name' => 'Douala', 'country' => 'Cameroun']);

    expect($result->id)->toBe($city->id);
    expect($result->wasRecentlyCreated)->toBeFalse();
});

it('throws InvalidArgumentException when nominatim returns empty', function (): void {
    Http::fake(['*nominatim*' => Http::response([], 200)]);

    expect(fn () => (new FindOrCreateCityAction)->handle(['name' => 'xkzqjj999']))
        ->toThrow(InvalidArgumentException::class);
});

it('creates city from valid nominatim response', function (): void {
    Http::fake([
        '*nominatim*' => Http::response([[
            'name' => 'Bafoussam',
            'lat' => '5.4737',
            'lon' => '10.4179',
            'address' => ['country' => 'Cameroun'],
        ]], 200),
    ]);

    $city = (new FindOrCreateCityAction)->handle(['name' => 'Bafoussam']);

    expect($city->name)->toBe('Bafoussam')
        ->and($city->country)->toBe('Cameroun')
        ->and($city->latitude)->toBeFloat()
        ->and($city->wasRecentlyCreated)->toBeTrue();
});

it('creates a non-african city (Geneva) with correct coords from nominatim', function (): void {
    Http::fake([
        '*nominatim*' => Http::response([[
            'name' => 'Genf',
            'lat' => '46.2017',
            'lon' => '6.1469',
            'address' => ['country' => 'Switzerland'],
        ]], 200),
    ]);

    $city = (new FindOrCreateCityAction)->handle(['name' => 'Genève']);

    expect($city->country)->toBe('Switzerland')
        ->and($city->latitude)->toBeFloat()
        ->and((float) $city->latitude)->toBeGreaterThan(40.0)
        ->and($city->wasRecentlyCreated)->toBeTrue();
});

// ─── GeoFindOrCreateController ───────────────────────────────────────────────

it('POST /geo/city returns 422 for unknown city name', function (): void {
    Http::fake(['*nominatim*' => Http::response([], 200)]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/geo/city', ['name' => 'zzznotacity9999'])
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

it('POST /geo/city returns 200 for existing city', function (): void {
    $city = City::factory()->create(['name' => 'Douala']);

    Http::preventStrayRequests();

    $this->actingAs($this->user)
        ->postJson('/api/v1/geo/city', ['name' => 'Douala'])
        ->assertOk()
        ->assertJsonPath('data.id', $city->id);
});

// ─── FindOrCreateQuarterAction ────────────────────────────────────────────────

it('FindOrCreateQuarterAction finds existing quarter case-insensitively', function (): void {
    $city = City::factory()->create();
    $quarter = Quarter::factory()->create(['name' => 'Akwa', 'city_id' => $city->id]);

    $result = (new FindOrCreateQuarterAction)->handle(['name' => 'akwa', 'city_id' => $city->id]);

    expect($result->id)->toBe($quarter->id);
});
