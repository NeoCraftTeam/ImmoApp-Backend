<?php

declare(strict_types=1);

use App\Services\Tour\QrCodeService;

// ===========================================================================
// TC-QR-01 — the branded QR raster must not squish the (square) QR matrix
// ---------------------------------------------------------------------------
// The branded SVG canvas is portrait (1100 × 1520 ≈ 0.72). Rasterising it into
// a forced square (resizeImage($size, $size)) scales it non-uniformly and
// squishes the embedded ISO QR matrix, breaking scannability. The rasteriser
// must preserve the source aspect ratio, so the PNG stays portrait.
// ===========================================================================

it('rasterises the branded QR without squishing the matrix', function (): void {
    if (!extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is required to assert QR rasterisation geometry.');
    }

    $png = new QrCodeService()->renderRichPng('https://keyhome.app/u/demo', 800);

    expect(str_starts_with($png, "\x89PNG"))->toBeTrue();

    $size = getimagesizefromstring($png);
    expect($size)->not->toBeFalse();

    [$width, $height] = $size;

    // Aspect ratio must track the source canvas (1100 / 1520 ≈ 0.72). A squished
    // raster forces a 1:1 square (width === height), so it stays portrait here.
    expect($width)->toBeLessThan($height);

    $aspect = $width / $height;
    expect($aspect)->toBeGreaterThan(0.60)
        ->and($aspect)->toBeLessThan(0.85);
});

// ===========================================================================
// TC-QR-02 — the plain (silhouette-free) placard QR stays genuinely square
// ===========================================================================

it('keeps the plain placard QR square', function (): void {
    $dataUri = new QrCodeService()->plainPngDataUriForUrl('https://keyhome.app/a/demo');

    expect($dataUri)->toStartWith('data:image/png;base64,');

    $png = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
    expect($png)->not->toBeFalse();

    $size = getimagesizefromstring((string) $png);
    expect($size)->not->toBeFalse();

    // Plain chillerlan QR is intrinsically square.
    expect($size[0])->toBe($size[1]);
});
