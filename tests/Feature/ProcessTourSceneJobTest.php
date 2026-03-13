<?php

declare(strict_types=1);

use App\Jobs\ProcessTourSceneJob;
use App\Models\Ad;
use App\Models\User;
use App\Services\PanoramaProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('updates the scene with cubemap face URLs on success', function (): void {
    Storage::fake();

    $owner = User::factory()->agents()->create();
    $config = [
        'default_scene' => 's1',
        'scenes' => [
            [
                'id' => 's1',
                'title' => 'Salon',
                'image_url' => '/tour-image/fake/salon.jpg',
                'processing' => true,
                'hotspots' => [],
            ],
        ],
    ];

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'has_3d_tour' => true,
            'tour_config' => $config,
        ]);
    });

    $facePaths = [
        'f' => "ads/{$ad->id}/tours/scenes/s1/faces/f.webp",
        'r' => "ads/{$ad->id}/tours/scenes/s1/faces/r.webp",
        'b' => "ads/{$ad->id}/tours/scenes/s1/faces/b.webp",
        'l' => "ads/{$ad->id}/tours/scenes/s1/faces/l.webp",
        'u' => "ads/{$ad->id}/tours/scenes/s1/faces/u.webp",
        'd' => "ads/{$ad->id}/tours/scenes/s1/faces/d.webp",
    ];

    $mock = mock(PanoramaProcessor::class);
    $mock->shouldReceive('generateCubeFaces')->once()->andReturn($facePaths);

    $job = new ProcessTourSceneJob(
        adId: (string) $ad->id,
        sceneId: 's1',
        panoramaType: 'cubemap',
        sourceR2Path: "ads/{$ad->id}/tours/salon.jpg",
    );

    $job->handle($mock);

    $updated = $ad->fresh();
    $scene = collect($updated->tour_config['scenes'])->firstWhere('id', 's1');

    expect($scene['processing'])->toBeFalse();
    expect($scene['cube_map'])->toHaveCount(6);
});

it('updates the scene with multires tile data on success', function (): void {
    Storage::fake();

    $owner = User::factory()->agents()->create();
    $config = [
        'default_scene' => 's1',
        'scenes' => [
            [
                'id' => 's1',
                'title' => 'Salon',
                'image_url' => '/tour-image/fake/salon.jpg',
                'processing' => true,
                'hotspots' => [],
            ],
        ],
    ];

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'has_3d_tour' => true,
            'tour_config' => $config,
        ]);
    });

    $facePaths = [
        'f' => "ads/{$ad->id}/tours/scenes/s1/faces/f.webp",
        'r' => "ads/{$ad->id}/tours/scenes/s1/faces/r.webp",
        'b' => "ads/{$ad->id}/tours/scenes/s1/faces/b.webp",
        'l' => "ads/{$ad->id}/tours/scenes/s1/faces/l.webp",
        'u' => "ads/{$ad->id}/tours/scenes/s1/faces/u.webp",
        'd' => "ads/{$ad->id}/tours/scenes/s1/faces/d.webp",
    ];

    $mock = mock(PanoramaProcessor::class);
    $mock->shouldReceive('generateCubeFaces')->once()->andReturn($facePaths);
    $mock->shouldReceive('generateTilePyramid')->once();
    $mock->shouldReceive('generateFallbackFaces')->once();

    $job = new ProcessTourSceneJob(
        adId: (string) $ad->id,
        sceneId: 's1',
        panoramaType: 'multires',
        sourceR2Path: "ads/{$ad->id}/tours/salon.jpg",
    );

    $job->handle($mock);

    $updated = $ad->fresh();
    $scene = collect($updated->tour_config['scenes'])->firstWhere('id', 's1');

    expect($scene['processing'])->toBeFalse();
    expect($scene['tiles_base_url'])->toBeString();
    expect($scene['fallback_base_url'])->toBeString();
    expect($scene['tiles_max_level'])->toBeInt();
    expect($scene['cube_resolution'])->toBe(2048);
});

it('marks scene as failed when an exception occurs', function (): void {
    Storage::fake();

    $owner = User::factory()->agents()->create();
    $config = [
        'default_scene' => 's1',
        'scenes' => [
            [
                'id' => 's1',
                'title' => 'Salon',
                'image_url' => '/tour-image/fake/salon.jpg',
                'processing' => true,
                'hotspots' => [],
            ],
        ],
    ];

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner, $config): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'has_3d_tour' => true,
            'tour_config' => $config,
        ]);
    });

    $job = new ProcessTourSceneJob(
        adId: (string) $ad->id,
        sceneId: 's1',
        panoramaType: 'cubemap',
        sourceR2Path: "ads/{$ad->id}/tours/salon.jpg",
    );

    $job->failed(new \RuntimeException('GD memory exhausted'));

    $updated = $ad->fresh();
    $scene = collect($updated->tour_config['scenes'])->firstWhere('id', 's1');

    expect($scene['processing'])->toBeFalse();
    expect($scene['processing_failed'])->toBeTrue();
});

it('does nothing when the ad no longer exists', function (): void {
    Storage::fake();

    $mock = mock(PanoramaProcessor::class);
    $mock->shouldNotReceive('generateCubeFaces');

    $job = new ProcessTourSceneJob(
        adId: '00000000-0000-0000-0000-000000000000',
        sceneId: 's1',
        panoramaType: 'cubemap',
        sourceR2Path: 'ads/fake/tours/salon.jpg',
    );

    $job->handle($mock);
});
