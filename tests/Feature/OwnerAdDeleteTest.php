<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows owner agent to delete their draft ad', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $agent): void {
        $ad = Ad::factory()->create([
            'user_id' => $agent->id,
            'status' => AdStatus::DRAFT,
        ]);
    });

    Sanctum::actingAs($agent, ['*']);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Annonce supprimée.');
    $this->assertSoftDeleted('ad', ['id' => $ad->id]);
});

it('returns generic not found when deleting another users ad', function (): void {
    $ownerAgent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $otherAgent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $ownerAgent): void {
        $ad = Ad::factory()->create([
            'user_id' => $ownerAgent->id,
            'status' => AdStatus::DRAFT,
        ]);
    });

    Sanctum::actingAs($otherAgent, ['*']);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Ressource introuvable.',
            'code' => 'NOT_FOUND',
        ]);
    $this->assertNotSoftDeleted('ad', ['id' => $ad->id]);
});

it('can permanently delete an already soft-deleted ad from my ads list', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $agent): void {
        $ad = Ad::factory()->create([
            'user_id' => $agent->id,
            'status' => AdStatus::DRAFT,
        ]);
        $ad->delete();
    });

    Sanctum::actingAs($agent, ['*']);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);
    $this->assertDatabaseMissing('ad', ['id' => $ad->id]);
});

it('returns generic not found for unknown ad id', function (): void {
    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    Sanctum::actingAs($agent, ['*']);

    $response = $this->deleteJson('/api/v1/ads/00000000-0000-0000-0000-000000000099');

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Ressource introuvable.',
            'code' => 'NOT_FOUND',
        ]);
});

it('returns the deleted ad id and image count in the payload', function (): void {
    Storage::fake('public');

    $agent = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $agent): void {
        $ad = Ad::factory()->create([
            'user_id' => $agent->id,
            'status' => AdStatus::DRAFT,
        ]);
        $ad->addMedia(UploadedFile::fake()->image('one.jpg'))->toMediaCollection('images');
        $ad->addMedia(UploadedFile::fake()->image('two.jpg'))->toMediaCollection('images');
    });

    Sanctum::actingAs($agent, ['*']);
    $response = $this->deleteJson("/api/v1/ads/{$ad->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Annonce supprimée.')
        ->assertJsonPath('data.deleted_ad_id', $ad->id)
        ->assertJsonPath('data.deleted_images_count', 2);
    $this->assertSoftDeleted('ad', ['id' => $ad->id]);
});
