<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/* ──────────────────────────────────────────────────────────────────
 * GET /api/v1/users/{identifier}/public-profile
 * ──────────────────────────────────────────────────────────────── */

test('public profile returns 404 for non-existent username', function (): void {
    $this->getJson('/api/v1/users/no-such-user/public-profile')
        ->assertNotFound();
});

test('public profile returns 404 for non-existent uuid', function (): void {
    $this->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000/public-profile')
        ->assertNotFound();
});

test('public profile resolves customer by username', function (): void {
    $user = User::factory()->customers()->create(['username' => 'jean-test-customer']);

    $response = $this->getJson('/api/v1/users/jean-test-customer/public-profile');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.username', 'jean-test-customer');
});

test('public profile resolves user by uuid', function (): void {
    $user = User::factory()->customers()->create();

    $this->getJson("/api/v1/users/{$user->id}/public-profile")
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('public profile for individual agent returns ads and correct structure', function (): void {
    $agent = User::factory()->agents()->state(['type' => 'individual'])->create();

    Ad::withoutSyncingToSearch(function () use ($agent): void {
        Ad::factory()->count(3)->for($agent)->create(['status' => 'available']);
        Ad::factory()->for($agent)->create(['status' => 'pending']);
    });

    $response = $this->getJson("/api/v1/users/{$agent->username}/public-profile");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'id', 'username', 'firstname', 'lastname', 'display_name',
                'type', 'is_verified', 'member_since', 'total_active_ads',
                'review_stats' => ['avg_rating', 'total_reviews'],
                'recent_reviews',
            ],
            'ads',
            'meta' => ['total', 'current_page', 'last_page', 'per_page'],
        ]);

    // Only available ads are returned
    expect($response->json('meta.total'))->toBe(3);
});

test('public profile for agency agent does not lazy-load agency on ads (regression)', function (): void {
    // Regression: Ad::getPublisherName() accesses $this->agency without a
    // relationLoaded guard. If 'agency' is not eager-loaded on the Ad query,
    // it triggers a lazy load which is disabled globally → HTTP 500.
    $agent = User::factory()->agents()->state(['type' => 'agency'])->create();

    Ad::withoutSyncingToSearch(function () use ($agent): void {
        Ad::factory()->count(2)->for($agent)->create(['status' => 'available']);
    });

    // Must be 200, not 500
    $this->getJson("/api/v1/users/{$agent->username}/public-profile")
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('public profile is accessible without authentication', function (): void {
    $user = User::factory()->customers()->create();

    $this->getJson("/api/v1/users/{$user->username}/public-profile")
        ->assertOk();
});
