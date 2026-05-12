<?php

declare(strict_types=1);

use App\Support\PanoramaAngles;

it('normalizes yaw to equivalent angle in -180..180', function (): void {
    expect(PanoramaAngles::normalizeYawDegrees(0.0))->toBe(0.0);
    expect(PanoramaAngles::normalizeYawDegrees(90.0))->toBe(90.0);
    expect(PanoramaAngles::normalizeYawDegrees(-90.0))->toBe(-90.0);
    expect(PanoramaAngles::normalizeYawDegrees(180.0))->toBe(-180.0);
    expect(PanoramaAngles::normalizeYawDegrees(-180.0))->toBe(-180.0);
    expect(PanoramaAngles::normalizeYawDegrees(270.0))->toBe(-90.0);
    expect(PanoramaAngles::normalizeYawDegrees(-200.0))->toBe(160.0);
    expect(PanoramaAngles::normalizeYawDegrees(720.0))->toBe(0.0);
});

it('returns zero for non-finite yaw', function (): void {
    expect(PanoramaAngles::normalizeYawDegrees(NAN))->toBe(0.0);
    expect(PanoramaAngles::normalizeYawDegrees(INF))->toBe(0.0);
    expect(PanoramaAngles::normalizeYawDegrees(-INF))->toBe(0.0);
});
