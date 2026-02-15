<?php

use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('recommendations endpoint returns 401 for guest', function (): void {
    $response = $this->getJson('/api/v1/recommendations');
    $response->assertStatus(401);
});

test('cold start returns mixed ads for user without history', function (): void {
    $user = User::factory()->create();
    Ad::factory(5)->create(['status' => 'available', 'created_at' => now()->subDays(1)]);
    Ad::factory(5)->create(['status' => 'available', 'created_at' => now()]);

    Sanctum::actingAs($user);
    $response = $this->getJson('/api/v1/recommendations');

    $response->assertStatus(200)
        ->assertJsonPath('meta.source', 'cold_start')
        ->assertJsonStructure([
            'data',
            'meta' => ['source', 'algorithm'],
        ]);
});

test('returns personalized recommendations for user with interaction history', function (): void {
    $user = User::factory()->create();

    // Create target ad the user interacted with
    $targetAd = Ad::factory()->create([
        'price' => 100000,
        'status' => 'available',
    ]);

    // Track interactions (new system uses AdInteraction)
    AdInteraction::create([
        'user_id' => $user->id,
        'ad_id' => $targetAd->id,
        'type' => AdInteraction::TYPE_VIEW,
        'created_at' => now()->subDay(),
    ]);

    AdInteraction::create([
        'user_id' => $user->id,
        'ad_id' => $targetAd->id,
        'type' => AdInteraction::TYPE_FAVORITE,
        'created_at' => now(),
    ]);

    // Create similar ads (potential recommendations)
    Ad::factory(3)->create([
        'price' => 100000,
        'type_id' => $targetAd->type_id,
        'status' => 'available',
    ]);

    Sanctum::actingAs($user);
    $response = $this->getJson('/api/v1/recommendations');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => ['source', 'algorithm'],
        ]);
});

test('ad view tracking is debounced', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['status' => 'available']);

    Sanctum::actingAs($user);

    // First view
    $this->postJson("/api/v1/ads/{$ad->id}/view")->assertStatus(204);

    // Second view within 5 minutes — should not create a duplicate
    $this->postJson("/api/v1/ads/{$ad->id}/view")->assertStatus(204);

    expect(
        AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->count()
    )->toBe(1);
});

test('favorite toggle works correctly', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['status' => 'available']);

    Sanctum::actingAs($user);

    // Favorite
    $response = $this->postJson("/api/v1/ads/{$ad->id}/favorite");
    $response->assertStatus(200)->assertJsonPath('is_favorited', true);

    // Unfavorite
    $response = $this->postJson("/api/v1/ads/{$ad->id}/favorite");
    $response->assertStatus(200)->assertJsonPath('is_favorited', false);

    // Re-favorite
    $response = $this->postJson("/api/v1/ads/{$ad->id}/favorite");
    $response->assertStatus(200)->assertJsonPath('is_favorited', true);
});

test('favorites list returns only favorited ads', function (): void {
    $user = User::factory()->create();
    $ad1 = Ad::factory()->create(['status' => 'available']);
    $ad2 = Ad::factory()->create(['status' => 'available']);

    Sanctum::actingAs($user);

    // Favorite both
    $this->postJson("/api/v1/ads/{$ad1->id}/favorite");
    $this->postJson("/api/v1/ads/{$ad2->id}/favorite");

    // Unfavorite ad2
    $this->postJson("/api/v1/ads/{$ad2->id}/favorite");

    // Only ad1 should be in favorites
    $response = $this->getJson('/api/v1/my/favorites');
    $response->assertStatus(200);

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($ad1->id)
        ->not->toContain($ad2->id);
});
