<?php

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── GET /api/v1/ads/{ad}/tour — public ─────────────────────────────────────────

describe('GET /api/v1/ads/{ad}/tour', function (): void {
    it('returns 404 when ad has no tour', function (): void {
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'has_3d_tour' => false,
                'tour_config' => null,
            ]);
        });

        $this->getJson("/api/v1/ads/{$ad->id}/tour")
            ->assertNotFound();
    });

    it('returns tour config when tour exists', function (): void {
        $owner = User::factory()->agents()->create();
        $config = [
            'default_scene' => 'salon',
            'scenes' => [
                ['id' => 'salon', 'title' => 'Salon', 'image_url' => 'https://example.com/salon.jpg', 'hotspots' => []],
            ],
        ];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $config, $owner): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'user_id' => $owner->id,
                'has_3d_tour' => true,
                'tour_config' => $config,
                'tour_published_at' => now(),
            ]);
        });

        $this->actingAs($owner, 'sanctum');

        $this->getJson("/api/v1/ads/{$ad->id}/tour")
            ->assertOk()
            ->assertJsonPath('has_tour', true)
            ->assertJsonStructure(['has_tour', 'scenes_count', 'tour_published_at', 'config']);
    });

    it('returns 403 for guest when tour exists but is locked', function (): void {
        $owner = User::factory()->agents()->create();
        $config = [
            'default_scene' => 'salon',
            'scenes' => [
                ['id' => 'salon', 'title' => 'Salon', 'image_url' => 'https://example.com/salon.jpg', 'hotspots' => []],
            ],
        ];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create([
                'status' => AdStatus::AVAILABLE,
                'user_id' => $owner->id,
                'has_3d_tour' => true,
                'tour_config' => $config,
                'tour_published_at' => now(),
            ]);
        });

        $this->getJson("/api/v1/ads/{$ad->id}/tour")
            ->assertForbidden();
    });
});

// ── POST /panel-api/v1/ads/{ad}/tour/scenes — owner only ───────────────────────

describe('POST /panel-api/v1/ads/{ad}/tour/scenes', function (): void {
    it('redirects unauthenticated users', function (): void {
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad): void {
            $ad = Ad::factory()->create();
        });

        $this->post("/panel-api/v1/ads/{$ad->id}/tour/scenes")
            ->assertRedirect();
    });

    it('forbids a non-owner from uploading', function (): void {
        Storage::fake('r2');
        $owner = User::factory()->agents()->create();
        $other = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id]);
        });

        $this->actingAs($other);

        $this->post("/panel-api/v1/ads/{$ad->id}/tour/scenes", [
            'scenes' => [
                ['title' => 'Salon', 'image' => UploadedFile::fake()->image('salon.jpg')],
            ],
        ])->assertForbidden();
    });

    it('allows the owner to upload scenes and returns 201', function (): void {
        Storage::fake('r2');
        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => false]);
        });

        $this->actingAs($owner);

        $this->post("/panel-api/v1/ads/{$ad->id}/tour/scenes", [
            'scenes' => [
                ['title' => 'Salon', 'image' => UploadedFile::fake()->image('salon.jpg')],
                ['title' => 'Chambre', 'image' => UploadedFile::fake()->image('chambre.jpg')],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('scenes_count', 2)
            ->assertJsonStructure(['message', 'scenes_count', 'config']);

        expect($ad->fresh()->has_3d_tour)->toBeTrue();
    });

    it('rejects invalid file types', function (): void {
        Storage::fake('r2');
        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id]);
        });

        $this->actingAs($owner);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post("/panel-api/v1/ads/{$ad->id}/tour/scenes", [
                'scenes' => [
                    ['title' => 'Salon', 'image' => UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf')],
                ],
            ])->assertUnprocessable();
    });

    it('rejects when scenes array is missing', function (): void {
        $owner = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id]);
        });

        $this->actingAs($owner);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post("/panel-api/v1/ads/{$ad->id}/tour/scenes", [])
            ->assertUnprocessable();
    });
});

// ── PATCH /panel-api/v1/ads/{ad}/tour/scenes/{sceneId}/hotspots — owner only ───

describe('PATCH /panel-api/v1/ads/{ad}/tour/scenes/{sceneId}/hotspots', function (): void {
    it('redirects unauthenticated users', function (): void {
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad): void {
            $ad = Ad::factory()->create();
        });

        $this->patch("/panel-api/v1/ads/{$ad->id}/tour/scenes/scene-1/hotspots")
            ->assertRedirect();
    });

    it('forbids a non-owner from updating hotspots', function (): void {
        $owner = User::factory()->agents()->create();
        $other = User::factory()->agents()->create();
        $config = ['default_scene' => 'salon', 'scenes' => [['id' => 'salon', 'title' => 'Salon', 'hotspots' => []]]];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true, 'tour_config' => $config]);
        });

        $this->actingAs($other);

        $this->patchJson("/panel-api/v1/ads/{$ad->id}/tour/scenes/salon/hotspots", [
            'hotspots' => [['pitch' => 10, 'yaw' => 20, 'target_scene' => 'chambre', 'label' => 'Chambre']],
        ])->assertForbidden();
    });

    it('allows the owner to update hotspots', function (): void {
        $owner = User::factory()->agents()->create();
        $config = ['default_scene' => 'salon', 'scenes' => [
            ['id' => 'salon', 'title' => 'Salon', 'image_url' => 'https://s3.example.com/salon.jpg', 'hotspots' => []],
            ['id' => 'chambre', 'title' => 'Chambre', 'image_url' => 'https://s3.example.com/chambre.jpg', 'hotspots' => []],
        ]];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true, 'tour_config' => $config]);
        });

        $this->actingAs($owner);

        $this->patchJson("/panel-api/v1/ads/{$ad->id}/tour/scenes/salon/hotspots", [
            'hotspots' => [
                ['pitch' => 5.0, 'yaw' => -30.0, 'target_scene' => 'chambre', 'label' => 'Vers la chambre'],
            ],
        ])->assertOk()->assertJsonPath('message', 'Hotspots mis à jour.');

        $updated = $ad->fresh();
        $scenes = collect($updated->tour_config['scenes']);
        $salonHotspots = $scenes->firstWhere('id', 'salon')['hotspots'] ?? [];
        expect($salonHotspots)->toHaveCount(1);
    });

    it('rejects invalid hotspot pitch values', function (): void {
        $owner = User::factory()->agents()->create();
        $config = ['default_scene' => 'salon', 'scenes' => [['id' => 'salon', 'title' => 'Salon', 'hotspots' => []]]];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true, 'tour_config' => $config]);
        });

        $this->actingAs($owner);

        $this->patchJson("/panel-api/v1/ads/{$ad->id}/tour/scenes/salon/hotspots", [
            'hotspots' => [['pitch' => 999, 'yaw' => 0, 'target_scene' => 'salon', 'label' => 'Bad']],
        ])->assertUnprocessable();
    });

    it('rejects hotspot that targets its own scene', function (): void {
        $owner = User::factory()->agents()->create();
        $config = ['default_scene' => 'salon', 'scenes' => [
            ['id' => 'salon', 'title' => 'Salon', 'hotspots' => []],
            ['id' => 'chambre', 'title' => 'Chambre', 'hotspots' => []],
        ]];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true, 'tour_config' => $config]);
        });

        $this->actingAs($owner);

        $this->patchJson("/panel-api/v1/ads/{$ad->id}/tour/scenes/salon/hotspots", [
            'hotspots' => [['pitch' => 10, 'yaw' => 5, 'target_scene' => 'salon', 'label' => 'Self-ref']],
        ])->assertUnprocessable();
    });

    it('rejects hotspot target scene that does not exist', function (): void {
        $owner = User::factory()->agents()->create();
        $config = ['default_scene' => 'salon', 'scenes' => [
            ['id' => 'salon', 'title' => 'Salon', 'hotspots' => []],
            ['id' => 'chambre', 'title' => 'Chambre', 'hotspots' => []],
        ]];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true, 'tour_config' => $config]);
        });

        $this->actingAs($owner);

        $this->patchJson("/panel-api/v1/ads/{$ad->id}/tour/scenes/salon/hotspots", [
            'hotspots' => [['pitch' => 10, 'yaw' => 5, 'target_scene' => 'invalide', 'label' => 'Mauvais lien']],
        ])->assertUnprocessable();
    });

    it('rejects more than 50 hotspots per scene', function (): void {
        $owner = User::factory()->agents()->create();
        $config = ['default_scene' => 'salon', 'scenes' => [
            ['id' => 'salon', 'title' => 'Salon', 'hotspots' => []],
            ['id' => 'chambre', 'title' => 'Chambre', 'hotspots' => []],
        ]];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true, 'tour_config' => $config]);
        });

        $this->actingAs($owner);

        $hotspots = array_map(fn (int $i) => [
            'pitch' => $i % 90,
            'yaw' => $i % 180,
            'target_scene' => 'chambre',
            'label' => "Hotspot {$i}",
        ], range(1, 51));

        $this->patchJson("/panel-api/v1/ads/{$ad->id}/tour/scenes/salon/hotspots", [
            'hotspots' => $hotspots,
        ])->assertUnprocessable();
    });
});

// ── DELETE /panel-api/v1/ads/{ad}/tour — owner only ────────────────────────────

describe('DELETE /panel-api/v1/ads/{ad}/tour', function (): void {
    it('redirects unauthenticated users', function (): void {
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad): void {
            $ad = Ad::factory()->create(['has_3d_tour' => true]);
        });

        $this->delete("/panel-api/v1/ads/{$ad->id}/tour")
            ->assertRedirect();
    });

    it('forbids a non-owner from deleting the tour', function (): void {
        Storage::fake('r2');
        $owner = User::factory()->agents()->create();
        $other = User::factory()->agents()->create();
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true]);
        });

        $this->actingAs($other);

        $this->delete("/panel-api/v1/ads/{$ad->id}/tour")
            ->assertForbidden();
    });

    it('allows the owner to delete the tour', function (): void {
        Storage::fake('r2');
        $owner = User::factory()->agents()->create();
        $config = ['default_scene' => 'salon', 'scenes' => [
            ['id' => 'salon', 'title' => 'Salon', 'image_url' => 'tours/test/salon.jpg', 'hotspots' => []],
        ]];
        $ad = null;
        Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
            $ad = Ad::factory()->create(['user_id' => $owner->id, 'has_3d_tour' => true, 'tour_config' => $config]);
        });

        $this->actingAs($owner);

        $this->delete("/panel-api/v1/ads/{$ad->id}/tour")
            ->assertOk()
            ->assertJsonPath('message', 'Tour 3D supprimé.');

        $refreshed = $ad->fresh();
        expect($refreshed->has_3d_tour)->toBeFalse();
        expect($refreshed->tour_config)->toBeNull();
    });
});
