<?php

use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ══════════════════════════════════════════════════════════════
// TRACKING ENDPOINTS
// ══════════════════════════════════════════════════════════════

test('impression tracking is debounced at 30 seconds', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['status' => 'available']);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/ads/{$ad->id}/impression")->assertStatus(204);
    $this->postJson("/api/v1/ads/{$ad->id}/impression")->assertStatus(204);

    expect(
        AdInteraction::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('type', 'impression')
            ->count()
    )->toBe(1);
});

test('share tracking creates an interaction', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['status' => 'available']);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/ads/{$ad->id}/share")->assertStatus(204);
    $this->postJson("/api/v1/ads/{$ad->id}/share")->assertStatus(204);

    expect(
        AdInteraction::where('user_id', $user->id)
            ->where('type', 'share')
            ->count()
    )->toBe(2); // Not debounced
});

test('contact click tracking is debounced at 60 seconds', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['status' => 'available']);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/ads/{$ad->id}/contact-click")->assertStatus(204);
    $this->postJson("/api/v1/ads/{$ad->id}/contact-click")->assertStatus(204);

    expect(
        AdInteraction::where('user_id', $user->id)
            ->where('type', 'contact_click')
            ->count()
    )->toBe(1);
});

test('phone click tracking is debounced at 60 seconds', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['status' => 'available']);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/ads/{$ad->id}/phone-click")->assertStatus(204);
    $this->postJson("/api/v1/ads/{$ad->id}/phone-click")->assertStatus(204);

    expect(
        AdInteraction::where('user_id', $user->id)
            ->where('type', 'phone_click')
            ->count()
    )->toBe(1);
});

// ══════════════════════════════════════════════════════════════
// ANALYTICS ENDPOINTS
// ══════════════════════════════════════════════════════════════

test('analytics overview requires authentication', function (): void {
    $this->getJson('/api/v1/my/ads/analytics')->assertStatus(401);
});

test('analytics overview returns empty data for user with no ads', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/my/ads/analytics');
    $response->assertStatus(200)
        ->assertJsonPath('data.totals.impressions', 0)
        ->assertJsonPath('data.totals.views', 0)
        ->assertJsonPath('data.top_ads', []);
});

test('analytics overview aggregates all interaction types', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $user->id, 'status' => 'available']);
    $viewer = User::factory()->create();

    // Create various interactions
    foreach (['view', 'view', 'view', 'impression', 'impression', 'favorite', 'share', 'contact_click', 'phone_click', 'unlock'] as $type) {
        AdInteraction::create([
            'user_id' => $viewer->id,
            'ad_id' => $ad->id,
            'type' => $type,
            'created_at' => now()->subDays(rand(1, 10)),
        ]);
    }

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/my/ads/analytics');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'period',
                'totals' => [
                    'impressions',
                    'views',
                    'favorites',
                    'shares',
                    'contact_clicks',
                    'phone_clicks',
                    'unlocks',
                    'conversion_rate',
                    'engagement_rate',
                ],
                'trends',
                'top_ads',
            ],
        ]);

    $totals = $response->json('data.totals');
    expect($totals['views'])->toBe(3);
    expect($totals['impressions'])->toBe(2);
    expect($totals['favorites'])->toBe(1);
    expect($totals['shares'])->toBe(1);
    expect($totals['contact_clicks'])->toBe(1);
    expect($totals['phone_clicks'])->toBe(1);
    expect($totals['unlocks'])->toBe(1);
});

test('analytics overview supports period filtering', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $user->id, 'status' => 'available']);
    $viewer = User::factory()->create();

    // Old interaction (60 days ago)
    AdInteraction::create([
        'user_id' => $viewer->id,
        'ad_id' => $ad->id,
        'type' => 'view',
        'created_at' => now()->subDays(60),
    ]);

    // Recent interaction (2 days ago)
    AdInteraction::create([
        'user_id' => $viewer->id,
        'ad_id' => $ad->id,
        'type' => 'view',
        'created_at' => now()->subDays(2),
    ]);

    Sanctum::actingAs($user);

    // 7d: only the recent view
    $response = $this->getJson('/api/v1/my/ads/analytics?period=7d');
    expect($response->json('data.totals.views'))->toBe(1);

    // 90d: both views
    $response = $this->getJson('/api/v1/my/ads/analytics?period=90d');
    expect($response->json('data.totals.views'))->toBe(2);
});

test('single ad analytics requires ownership', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);

    Sanctum::actingAs($otherUser);

    $this->getJson("/api/v1/my/ads/{$ad->id}/analytics")->assertStatus(403);
});

test('single ad analytics returns detailed metrics', function (): void {
    $owner = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    $viewer1 = User::factory()->create();
    $viewer2 = User::factory()->create();

    // Viewer 1: views twice, favorites once
    AdInteraction::create(['user_id' => $viewer1->id, 'ad_id' => $ad->id, 'type' => 'view', 'created_at' => now()->subDays(3)]);
    AdInteraction::create(['user_id' => $viewer1->id, 'ad_id' => $ad->id, 'type' => 'view', 'created_at' => now()->subDays(1)]);
    AdInteraction::create(['user_id' => $viewer1->id, 'ad_id' => $ad->id, 'type' => 'favorite', 'created_at' => now()->subDay()]);

    // Viewer 2: views once, contacts
    AdInteraction::create(['user_id' => $viewer2->id, 'ad_id' => $ad->id, 'type' => 'view', 'created_at' => now()->subDays(2)]);
    AdInteraction::create(['user_id' => $viewer2->id, 'ad_id' => $ad->id, 'type' => 'contact_click', 'created_at' => now()->subDay()]);

    Sanctum::actingAs($owner);

    $response = $this->getJson("/api/v1/my/ads/{$ad->id}/analytics");
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'period',
                'ad' => ['id', 'title', 'status'],
                'totals',
                'daily',
                'funnel' => [
                    'impressions',
                    'views',
                    'contacts',
                    'unlocks',
                    'impression_to_view_rate',
                    'view_to_contact_rate',
                    'view_to_unlock_rate',
                ],
                'audience' => ['unique_viewers', 'repeat_viewers', 'favorited_by'],
            ],
        ]);

    $audience = $response->json('data.audience');
    expect($audience['unique_viewers'])->toBe(2);
    expect($audience['repeat_viewers'])->toBe(1); // viewer1 viewed twice
    expect($audience['favorited_by'])->toBe(1);
});
