<?php

declare(strict_types=1);

/**
 * M-1 : Tests d'isolation multi-tenant.
 *
 * Vérifie qu'un bailleur (agent) ne peut pas accéder aux données privées
 * d'un autre bailleur via l'API : annonces, dépenses, documents.
 *
 * Chaque test dispose d'un owner1 (bailleur authentifié) et d'un owner2
 * (autre bailleur dont les ressources doivent être inaccessibles à owner1).
 */

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── Annonces (/my/ads) ────────────────────────────────────────────────────────

it('GET /my/ads returns only the authenticated bailleur own ads', function (): void {
    $owner1 = User::factory()->agents()->create();
    $owner2 = User::factory()->agents()->create();

    Ad::withoutSyncingToSearch(function () use ($owner1, $owner2): void {
        Ad::factory()->count(3)->create(['user_id' => $owner1->id]);
        Ad::factory()->count(5)->create(['user_id' => $owner2->id]);
    });

    Sanctum::actingAs($owner1, ['role:agent']);
    $this->getJson('/api/v1/my/ads')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /my/ads does not leak other bailleur draft ads', function (): void {
    $owner1 = User::factory()->agents()->create();
    $owner2 = User::factory()->agents()->create();

    Ad::withoutSyncingToSearch(function () use ($owner1, $owner2): void {
        Ad::factory()->create(['user_id' => $owner1->id, 'status' => 'available']);
        Ad::factory()->create(['user_id' => $owner2->id, 'status' => 'draft', 'is_visible' => false]);
    });

    Sanctum::actingAs($owner1, ['role:agent']);
    // owner1 has 1 available ad; owner2's draft must not leak → exactly 1 result
    $this->getJson('/api/v1/my/ads')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Dépenses (/my/ads/{ad}/expenses) ─────────────────────────────────────────

it('GET /my/ads/{ad}/expenses returns 403 when accessing another bailleur ad', function (): void {
    $owner1 = User::factory()->agents()->create();
    $owner2 = User::factory()->agents()->create();

    $ad2 = null;
    Ad::withoutSyncingToSearch(function () use ($owner2, &$ad2): void {
        $ad2 = Ad::factory()->create(['user_id' => $owner2->id]);
    });

    Sanctum::actingAs($owner1);
    $this->getJson("/api/v1/my/ads/{$ad2->id}/expenses")
        ->assertForbidden();
});

it('GET /my/ads/{ad}/expenses returns 200 for the ads own bailleur', function (): void {
    $owner = User::factory()->agents()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use ($owner, &$ad): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/my/ads/{$ad->id}/expenses")
        ->assertOk();
});

// ── Documents (/my/ads/{ad}/documents) ───────────────────────────────────────

it('GET /my/ads/{ad}/documents returns 403 when accessing another bailleur ad', function (): void {
    $owner1 = User::factory()->agents()->create();
    $owner2 = User::factory()->agents()->create();

    $ad2 = null;
    Ad::withoutSyncingToSearch(function () use ($owner2, &$ad2): void {
        $ad2 = Ad::factory()->create(['user_id' => $owner2->id]);
    });

    Sanctum::actingAs($owner1);
    $this->getJson("/api/v1/my/ads/{$ad2->id}/documents")
        ->assertForbidden();
});

it('GET /my/ads/{ad}/documents returns 200 for the ads own bailleur', function (): void {
    $owner = User::factory()->agents()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use ($owner, &$ad): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/my/ads/{$ad->id}/documents")
        ->assertOk();
});

// ── Profit/Loss (/my/ads/{ad}/profit-loss) ────────────────────────────────────

it('GET /my/ads/{ad}/profit-loss returns 403 when accessing another bailleur ad', function (): void {
    $owner1 = User::factory()->agents()->create();
    $owner2 = User::factory()->agents()->create();

    $ad2 = null;
    Ad::withoutSyncingToSearch(function () use ($owner2, &$ad2): void {
        $ad2 = Ad::factory()->create(['user_id' => $owner2->id]);
    });

    Sanctum::actingAs($owner1);
    $this->getJson("/api/v1/my/ads/{$ad2->id}/profit-loss")
        ->assertForbidden();
});
