<?php

use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('can show a single ad', function (): void {
    $user = User::factory()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $user): void {
        $ad = Ad::factory()->create(['user_id' => $user->id, 'status' => 'available']);
    });

    $response = $this->getJson("/api/v1/ads/{$ad->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $ad->id)
        ->assertJsonPath('data.title', $ad->title);
});

it('returns 404 for non-existent ad', function (): void {
    $fakeUuid = '00000000-0000-0000-0000-000000000000';

    $response = $this->getJson("/api/v1/ads/{$fakeUuid}");

    $response->assertNotFound();
});

it('agent can create an ad', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $data = [
        'title' => 'Test Ad',
        'description' => 'A test description',
        'adresse' => '123 Test Street',
        'price' => 50000,
        'surface_area' => 100,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'has_parking' => 'true',
        'latitude' => 4.05,
        'longitude' => 9.76,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
        'expires_at' => now()->addDays(30)->toDateTimeString(),
    ];

    Sanctum::actingAs($agent);
    $response = $this->postJson('/api/v1/ads', $data);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.ad.title', 'Test Ad');
    $this->assertDatabaseHas('ad', ['title' => 'Test Ad', 'user_id' => $agent->id, 'status' => 'pending']);
});

it('customer cannot create an ad', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $data = [
        'title' => 'Test Ad',
        'description' => 'A test description',
        'adresse' => '123 Test Street',
        'price' => 50000,
        'surface_area' => 100,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'has_parking' => 'true',
        'latitude' => 4.05,
        'longitude' => 9.76,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
    ];

    Sanctum::actingAs($customer);
    $response = $this->postJson('/api/v1/ads', $data);

    $response->assertForbidden();
});

it('unauthenticated user cannot create an ad', function (): void {
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();
    $data = [
        'title' => 'Test Ad',
        'description' => 'A test description',
        'adresse' => '123 Test Street',
        'price' => 50000,
        'surface_area' => 100,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'has_parking' => 'true',
        'latitude' => 4.05,
        'longitude' => 9.76,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
    ];

    $response = $this->postJson('/api/v1/ads', $data);

    $response->assertUnauthorized();
});

it('create ad validation fails with missing fields', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);

    Sanctum::actingAs($agent);
    $response = $this->postJson('/api/v1/ads', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'description', 'adresse', 'price', 'surface_area', 'bedrooms', 'bathrooms', 'has_parking', 'latitude', 'longitude', 'quarter_id', 'type_id']);
});

it('admin can update an ad', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    Sanctum::actingAs($admin);
    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'adresse' => '456 New Street',
        'price' => 75000,
        'surface_area' => 120,
        'bedrooms' => 4,
        'bathrooms' => 2,
        'has_parking' => 'true',
        'quarter_id' => $ad->quarter_id,
        'type_id' => $ad->type_id,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);
    $this->assertDatabaseHas('ad', ['id' => $ad->id, 'title' => 'Updated Title']);
});

it('non-admin cannot update an ad', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $agent): void {
        $ad = Ad::factory()->create(['user_id' => $agent->id, 'status' => 'available']);
    });

    Sanctum::actingAs($agent);
    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'Hacked Title',
        'quarter_id' => $ad->quarter_id,
        'type_id' => $ad->type_id,
    ]);

    $response->assertForbidden();
});

it('admin can delete an ad', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    Sanctum::actingAs($admin);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);
    $this->assertSoftDeleted('ad', ['id' => $ad->id]);
});

it('owner agent can delete their ad', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $agent): void {
        $ad = Ad::factory()->create(['user_id' => $agent->id, 'status' => 'available']);
    });

    Sanctum::actingAs($agent);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);
    $this->assertSoftDeleted('ad', ['id' => $ad->id]);
});

it('agent cannot delete another agents ad', function (): void {
    $ownerAgent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $otherAgent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $ownerAgent): void {
        $ad = Ad::factory()->create(['user_id' => $ownerAgent->id, 'status' => 'available']);
    });

    Sanctum::actingAs($otherAgent);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertForbidden();
    $this->assertNotSoftDeleted('ad', ['id' => $ad->id]);
});

it('unauthenticated user cannot delete an ad', function (): void {
    $user = User::factory()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $user): void {
        $ad = Ad::factory()->create(['user_id' => $user->id, 'status' => 'available']);
    });

    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertUnauthorized();
    $this->assertNotSoftDeleted('ad', ['id' => $ad->id]);
});
