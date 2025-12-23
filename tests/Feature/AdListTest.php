<?php

use App\Models\Ad;

test('anyone can view ad list', function (): void {
    Ad::factory(3)->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('single ad response structure is correct', function (): void {
    $ad = Ad::factory()->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads/'.$ad->id);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'price',
                'location' => ['latitude', 'longitude'],
                'images',
                'user' => ['id', 'firstname'],
                'quarter',
            ],
        ]);
});
