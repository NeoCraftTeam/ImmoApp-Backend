<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates a draft in the database when Meilisearch is unreachable', function (): void {
    config()->set('scout.driver', 'meilisearch');
    config()->set('scout.meilisearch.host', 'http://127.0.0.1:59998');

    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $quarter = Quarter::factory()->create();
    $adType = AdType::factory()->create();

    Sanctum::actingAs($agent, ['*']);

    $response = $this->postJson('/api/v1/ads', [
        'is_draft' => true,
        'title' => 'Brouillon sans Meili',
        'description' => '—',
        'adresse' => '—',
        'price' => 1,
        'surface_area' => 1,
        'bedrooms' => 0,
        'bathrooms' => 0,
        'has_parking' => '0',
        'latitude' => 4.05,
        'longitude' => 9.76,
        'quarter_id' => $quarter->id,
        'type_id' => $adType->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('ad', [
        'title' => 'Brouillon sans Meili',
        'user_id' => $agent->id,
        'status' => 'draft',
    ]);
});

it('updates a draft when Meilisearch is unreachable', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = Ad::factory()->for($agent)->create(['status' => AdStatus::DRAFT]);

    config()->set('scout.driver', 'meilisearch');
    config()->set('scout.meilisearch.host', 'http://127.0.0.1:59998');

    Sanctum::actingAs($agent, ['*']);

    $response = $this->putJson("/api/v1/ads/{$ad->id}", [
        'title' => 'Titre après sync échouée',
        'quarter_id' => $ad->quarter_id,
        'type_id' => $ad->type_id,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('ad', [
        'id' => $ad->id,
        'title' => 'Titre après sync échouée',
    ]);
});
