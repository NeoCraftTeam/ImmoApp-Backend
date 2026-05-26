<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
});

it('returns city list', function (): void {
    City::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/cities')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name']]]);    
});

it('filters cities by name ilike', function (): void {
    City::factory()->create(['name' => 'Douala']);
    City::factory()->create(['name' => 'Yaoundé']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/cities?q=doua')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('exposes country and coordinates in city resource', function (): void {
    City::factory()->create([
        'name'      => 'Kribi',
        'country'   => 'Cameroun',
        'latitude'  => 2.9395,
        'longitude' => 9.9086,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/cities?q=Kribi')
        ->assertOk()
        ->assertJsonFragment(['country' => 'Cameroun']);
});
