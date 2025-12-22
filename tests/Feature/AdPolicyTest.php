<?php

use App\Models\Ad;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('user can update own ad', function () {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);
    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'Updated Title',
        'description' => 'New description',
        'price' => 150000,
        // Champs obligatoires selon AdRequest... attention si validation stricte
        'adresse' => 'New Address',
        'surface_area' => 50,
        'bedrooms' => 2,
        'bathrooms' => 1,
        'has_parking' => 'true',
        'quarter_id' => $ad->quarter_id,
        'type_id' => $ad->type_id,
        'status' => 'available', // Important si validation enum
    ]);

    // Si ça échoue 422, c'est validation error. Si 403, c'est policy error.
    // On veut 200 ici.
    $response->assertStatus(200);

    $this->assertDatabaseHas('ad', [
        'id' => $ad->id,
        'title' => 'Updated Title',
    ]);
});

test('user cannot update other user ad', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs($attacker);
    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'Hacked Title',
    ]);

    $response->assertStatus(403); // Forbidden
});

test('user cannot delete other user ad', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create(['role' => 'customer']); // Force Customer
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs($attacker);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertStatus(403); // Forbidden

    // Vérifier qu'elle est toujours là (pas soft deleted)
    $this->assertNotSoftDeleted('ad', ['id' => $ad->id]);
});
