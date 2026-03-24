<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helpers for spherical panorama angles from client viewers (e.g. Photo Sphere Viewer),
 * which may report yaw outside [-180, 180] degrees.
 */
final class PanoramaAngles
{
    /**
     * Map yaw to an equivalent angle in [-180, 180] (degrees).
     */
    public static function normalizeYawDegrees(float $yaw): float
    {
        if (!is_finite($yaw)) {
            return 0.0;
        }

        $y = fmod($yaw + 180.0, 360.0);
        if ($y < 0) {
            $y += 360.0;
        }

        return $y - 180.0;
    }
}
