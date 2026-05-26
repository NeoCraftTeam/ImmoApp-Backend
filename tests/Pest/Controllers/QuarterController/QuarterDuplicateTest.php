<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Quarter;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->city  = City::factory()->create(['name' => 'Douala']);
});

it('rejects duplicate quarter in same city (case-insensitive)', function (): void {
    Quarter::factory()->create(['name' => 'Akwa', 'city_id' => $this->city->id]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/quarters', ['name' => 'akwa', 'city_id' => $this->city->id])
        ->assertStatus(409);
});

it('allows same quarter name in different cities', function (): void {
    $city2 = City::factory()->create(['name' => 'Yaoundé']);
    Quarter::factory()->create(['name' => 'Centre', 'city_id' => $this->city->id]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/quarters', ['name' => 'Centre', 'city_id' => $city2->id])
        ->assertStatus(200);
});

it('filters quarters by city_id', function (): void {
    Quarter::factory()->count(3)->create(['city_id' => $this->city->id]);
    $otherCity = City::factory()->create();
    Quarter::factory()->count(2)->create(['city_id' => $otherCity->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/quarters?city_id='.$this->city->id.'&per_page=50')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
