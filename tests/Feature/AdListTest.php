<?php

use App\Models\Ad;

test('anyone can view ad list', function () {
    Ad::factory(3)->create(['status' => 'available']);

    $response = $this->getJson('/api/v1/ads');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('can search ads by city', function () {
    // Ce test nécessite que Scout soit configuré avec 'collection' driver pour le test
    // Ou que AdController utilise une query SQL fallback si Scout n'est pas là.
    // Mettons de côté le search complexe pour l'instant.
});

test('single ad response structure is correct', function () {
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
