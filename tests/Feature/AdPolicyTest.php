<?php

use App\Models\Ad;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('admin can update any ad', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs($admin);
    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'Updated Title',
        'description' => 'New description',
        'price' => 150000,
        'adresse' => 'New Address',
        'surface_area' => 50,
        'bedrooms' => 2,
        'bathrooms' => 1,
        'has_parking' => 'true',
        'quarter_id' => $ad->quarter_id,
        'type_id' => $ad->type_id,
        'status' => 'available',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('ad', [
        'id' => $ad->id,
        'title' => 'Updated Title',
    ]);
});

test('user cannot update other user ad', function (): void {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs($attacker);
    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'Hacked Title',
    ]);

    $response->assertStatus(403); // Forbidden
});

test('owner cannot update own ad (admin only)', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);
    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'My Update',
    ]);

    $response->assertStatus(403); // Only admins can update
});

test('user cannot delete other user ad', function (): void {
    $owner = User::factory()->create();
    $attacker = User::factory()->create(['role' => 'customer']);
    $ad = Ad::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs($attacker);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertStatus(403); // Forbidden

    $this->assertNotSoftDeleted('ad', ['id' => $ad->id]);
});
