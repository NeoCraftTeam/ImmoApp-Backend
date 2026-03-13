<?php

declare(strict_types=1);

use App\Models\Ad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('skips ads without 3D tours', function (): void {
    Ad::withoutSyncingToSearch(function (): void {
        Ad::factory()->create(['has_3d_tour' => false, 'tour_config' => null]);
    });

    $this->artisan('tour:backfill-pano-metadata')
        ->expectsOutputToContain('No ads with 3D tours found.')
        ->assertSuccessful();
});

it('skips scenes that already have haov metadata', function (): void {
    $config = [
        'default_scene' => 's1',
        'scenes' => [
            ['id' => 's1', 'title' => 'Salon', 'image_path' => 'ads/1/tours/salon.jpg', 'haov' => 360, 'vaov' => 180, 'vOffset' => 0, 'hotspots' => []],
        ],
    ];

    Ad::withoutSyncingToSearch(function () use ($config): void {
        Ad::factory()->create(['has_3d_tour' => true, 'tour_config' => $config]);
    });

    $this->artisan('tour:backfill-pano-metadata')
        ->expectsOutputToContain('skipping')
        ->assertSuccessful();
});

it('skips scenes without an image_path', function (): void {
    $config = [
        'default_scene' => 's1',
        'scenes' => [
            ['id' => 's1', 'title' => 'Salon', 'hotspots' => []],
        ],
    ];

    Ad::withoutSyncingToSearch(function () use ($config): void {
        Ad::factory()->create(['has_3d_tour' => true, 'tour_config' => $config]);
    });

    $this->artisan('tour:backfill-pano-metadata')
        ->expectsOutputToContain('no image_path')
        ->assertSuccessful();
});

it('backfills metadata from a stored image', function (): void {
    Storage::fake();

    $img = imagecreatetruecolor(4000, 2000);
    ob_start();
    imagejpeg($img);
    $jpegData = ob_get_clean();
    imagedestroy($img);

    Storage::put('ads/99/tours/salon.jpg', $jpegData);

    $config = [
        'default_scene' => 's1',
        'scenes' => [
            ['id' => 's1', 'title' => 'Salon', 'image_path' => 'ads/99/tours/salon.jpg', 'hotspots' => []],
        ],
    ];

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $config): void {
        $ad = Ad::factory()->create(['has_3d_tour' => true, 'tour_config' => $config]);
    });

    $this->artisan('tour:backfill-pano-metadata')
        ->assertSuccessful();

    $updated = $ad->fresh();
    $scene = $updated->tour_config['scenes'][0];
    expect($scene)->toHaveKeys(['haov', 'vaov', 'vOffset']);
    expect($scene['haov'])->toBeGreaterThan(0);
});

it('does not write in dry-run mode', function (): void {
    Storage::fake();

    $img = imagecreatetruecolor(4000, 2000);
    ob_start();
    imagejpeg($img);
    $jpegData = ob_get_clean();
    imagedestroy($img);

    Storage::put('ads/99/tours/salon.jpg', $jpegData);

    $config = [
        'default_scene' => 's1',
        'scenes' => [
            ['id' => 's1', 'title' => 'Salon', 'image_path' => 'ads/99/tours/salon.jpg', 'hotspots' => []],
        ],
    ];

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $config): void {
        $ad = Ad::factory()->create(['has_3d_tour' => true, 'tour_config' => $config]);
    });

    $this->artisan('tour:backfill-pano-metadata --dry-run')
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    $scene = $ad->fresh()->tour_config['scenes'][0];
    expect($scene)->not->toHaveKey('haov');
});

it('processes only the specified ad with --ad option', function (): void {
    Storage::fake();

    $config = [
        'default_scene' => 's1',
        'scenes' => [
            ['id' => 's1', 'title' => 'Salon', 'image_path' => 'ads/99/tours/salon.jpg', 'haov' => 360, 'vaov' => 180, 'vOffset' => 0, 'hotspots' => []],
        ],
    ];

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $config): void {
        $ad = Ad::factory()->create(['has_3d_tour' => true, 'tour_config' => $config]);
    });

    $this->artisan("tour:backfill-pano-metadata --ad={$ad->id}")
        ->assertSuccessful();
});
