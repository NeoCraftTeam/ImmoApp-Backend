<?php

declare(strict_types=1);

use App\Services\TourService;

it('returns default metadata for a non-existent file', function (): void {
    $service = new TourService;
    $result = $service->extractPanoMetadata('/tmp/non_existent_file_'.uniqid().'.jpg');

    expect($result)->toBe([
        'haov' => 360.0,
        'vaov' => 180.0,
        'vOffset' => 0.0,
        'is_partial' => false,
    ]);
});

it('estimates partial panorama from a wide aspect ratio image', function (): void {
    $tmpFile = tempnam(sys_get_temp_dir(), 'pano_test_');
    $width = 14848;
    $height = 1932;
    $img = imagecreatetruecolor($width, $height);
    imagejpeg($img, $tmpFile, 10);
    imagedestroy($img);

    $service = new TourService;
    $result = $service->extractPanoMetadata($tmpFile);

    expect($result['haov'])->toBe(360.0);
    expect($result['vaov'])->toBeGreaterThan(20.0)->toBeLessThan(60.0);
    expect($result['is_partial'])->toBeTrue();

    @unlink($tmpFile);
});

it('returns full sphere defaults for a 2:1 aspect ratio image', function (): void {
    $tmpFile = tempnam(sys_get_temp_dir(), 'pano_test_');
    $width = 4000;
    $height = 2000;
    $img = imagecreatetruecolor($width, $height);
    imagejpeg($img, $tmpFile, 10);
    imagedestroy($img);

    $service = new TourService;
    $result = $service->extractPanoMetadata($tmpFile);

    expect($result['haov'])->toBe(360.0);
    expect($result['vaov'])->toBe(180.0);
    expect($result['vOffset'])->toBe(0.0);
    expect($result['is_partial'])->toBeFalse();

    @unlink($tmpFile);
});
