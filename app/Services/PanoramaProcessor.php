<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Converts equirectangular 360° panoramas to cubemap face images and
 * Pannellum-compatible multires tile pyramids.
 *
 * This class performs pixel-by-pixel coordinate projection using GD.
 * It must only be called from a queued job — never from a HTTP request.
 */
class PanoramaProcessor
{
    /**
     * Face identifiers in the order Pannellum's cubeMap array expects:
     * front, right, back, left, up, down.
     */
    private const array FACES = ['f', 'r', 'b', 'l', 'u', 'd'];

    /**
     * Convert an equirectangular source image stored on R2 into 6 cube face WebPs.
     * Stores each face at {outputPrefix}/{face}.webp and returns their R2 paths.
     *
     * @return array<string, string> Keyed by face letter (f, r, b, l, u, d)
     */
    public function generateCubeFaces(string $sourceR2Path, string $outputPrefix, int $faceSize = 1024): array
    {
        ini_set('memory_limit', '512M');

        $raw = Storage::disk()->get($sourceR2Path);
        if ($raw === null) {
            throw new RuntimeException("Could not read source panorama: {$sourceR2Path}");
        }

        $src = imagecreatefromstring($raw);
        unset($raw);

        if ($src === false) {
            throw new RuntimeException("Failed to decode image: {$sourceR2Path}");
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $step = (float) ($faceSize - 1);

        $paths = [];

        foreach (self::FACES as $face) {
            $dst = imagecreatetruecolor($faceSize, $faceSize);

            if ($dst === false) {
                throw new RuntimeException("Failed to allocate GD image for face {$face}");
            }

            for ($py = 0; $py < $faceSize; $py++) {
                $ny = 1.0 - (2.0 * $py / $step);

                for ($px = 0; $px < $faceSize; $px++) {
                    $nx = (2.0 * $px / $step) - 1.0;

                    [$vx, $vy, $vz] = $this->faceToVector($face, $nx, $ny);

                    $len = sqrt($vx * $vx + $vy * $vy + $vz * $vz);
                    $lat = asin($vy / $len);
                    $lon = atan2($vx, $vz);

                    $u = max(0.0, min(1.0, 0.5 + $lon / (2.0 * M_PI)));
                    $v = max(0.0, min(1.0, 0.5 - $lat / M_PI));

                    $color = $this->bilinearSample($src, $srcW, $srcH, $u * ($srcW - 1), $v * ($srcH - 1));
                    imagesetpixel($dst, $px, $py, $color);
                }
            }

            ob_start();
            imagewebp($dst, null, 85);
            $webpData = (string) ob_get_clean();
            imagedestroy($dst);

            $r2Path = "{$outputPrefix}/{$face}.webp";
            Storage::disk()->put($r2Path, $webpData);
            $paths[$face] = $r2Path;
        }

        imagedestroy($src);

        return $paths;
    }

    /**
     * Generate a Pannellum multires tile pyramid from pre-generated cube face images stored on R2.
     * Tiles are stored at {tilesR2Prefix}/{level}/{face}{row}_{col}.webp
     *
     * @param  array<string, string>  $facePaths  R2 paths keyed by face letter
     */
    public function generateTilePyramid(
        array $facePaths,
        string $tilesR2Prefix,
        int $tileSize = 512,
        int $cubeResolution = 2048,
    ): void {
        $maxLevel = (int) ceil(log($cubeResolution / $tileSize, 2)) + 1;

        foreach ($facePaths as $face => $facePath) {
            $raw = Storage::disk()->get($facePath);
            $faceImg = imagecreatefromstring($raw);
            unset($raw);

            // Ensure the face image is at cubeResolution size.
            $faceW = imagesx($faceImg);
            if ($faceW !== $cubeResolution) {
                $rescaled = imagecreatetruecolor($cubeResolution, $cubeResolution);
                imagecopyresampled($rescaled, $faceImg, 0, 0, 0, 0, $cubeResolution, $cubeResolution, $faceW, $faceW);
                imagedestroy($faceImg);
                $faceImg = $rescaled;
            }

            for ($level = 1; $level <= $maxLevel; $level++) {
                $levelSize = $tileSize * (2 ** ($level - 1));
                $numTiles = (int) ($levelSize / $tileSize);

                $resized = imagecreatetruecolor($levelSize, $levelSize);
                imagecopyresampled($resized, $faceImg, 0, 0, 0, 0, $levelSize, $levelSize, $cubeResolution, $cubeResolution);

                for ($row = 0; $row < $numTiles; $row++) {
                    for ($col = 0; $col < $numTiles; $col++) {
                        $tile = imagecreatetruecolor($tileSize, $tileSize);
                        imagecopy($tile, $resized, 0, 0, $col * $tileSize, $row * $tileSize, $tileSize, $tileSize);

                        ob_start();
                        imagewebp($tile, null, 82);
                        $tileData = (string) ob_get_clean();
                        imagedestroy($tile);

                        Storage::disk()->put("{$tilesR2Prefix}/{$level}/{$face}{$row}_{$col}.webp", $tileData);
                    }
                }

                imagedestroy($resized);
            }

            imagedestroy($faceImg);
        }
    }

    /**
     * Generate small (256 px) fallback cube face images used by Pannellum
     * while multires tiles are still loading.
     *
     * @param  array<string, string>  $facePaths  R2 paths keyed by face letter
     * @return array<string, string> Fallback R2 paths keyed by face letter
     */
    public function generateFallbackFaces(array $facePaths, string $fallbackR2Prefix, int $fallbackSize = 256): array
    {
        $paths = [];

        foreach ($facePaths as $face => $facePath) {
            $raw = Storage::disk()->get($facePath);
            $faceImg = imagecreatefromstring($raw);
            unset($raw);

            $fallback = imagecreatetruecolor($fallbackSize, $fallbackSize);
            imagecopyresampled($fallback, $faceImg, 0, 0, 0, 0, $fallbackSize, $fallbackSize, imagesx($faceImg), imagesy($faceImg));
            imagedestroy($faceImg);

            ob_start();
            imagewebp($fallback, null, 75);
            $data = (string) ob_get_clean();
            imagedestroy($fallback);

            $r2Path = "{$fallbackR2Prefix}/{$face}.webp";
            Storage::disk()->put($r2Path, $data);
            $paths[$face] = $r2Path;
        }

        return $paths;
    }

    /**
     * Map a normalised cube face point (nx, ny ∈ [−1, 1]) to a 3-D direction vector.
     *
     * @return array{float, float, float}
     */
    private function faceToVector(string $face, float $nx, float $ny): array
    {
        return match ($face) {
            'f' => [$nx,   $ny,   1.0],   // front  (+Z)
            'b' => [-$nx,  $ny,  -1.0],   // back   (−Z)
            'r' => [1.0,   $ny,  -$nx],   // right  (+X)
            'l' => [-1.0,  $ny,   $nx],   // left   (−X)
            'u' => [$nx,   1.0,   $ny],   // up     (+Y)
            'd' => [$nx,  -1.0,  -$ny],   // down   (−Y)
            default => throw new \InvalidArgumentException("Unknown face: {$face}"),
        };
    }

    /**
     * Bilinear-interpolated pixel sample from a GD truecolor image.
     */
    private function bilinearSample(\GdImage $img, int $w, int $h, float $x, float $y): int
    {
        $x0 = (int) floor($x);
        $y0 = (int) floor($y);
        $x1 = min($x0 + 1, $w - 1);
        $y1 = min($y0 + 1, $h - 1);

        $fx = $x - $x0;
        $fy = $y - $y0;
        $rfx = 1.0 - $fx;
        $rfy = 1.0 - $fy;

        $c00 = imagecolorat($img, $x0, $y0);
        $c10 = imagecolorat($img, $x1, $y0);
        $c01 = imagecolorat($img, $x0, $y1);
        $c11 = imagecolorat($img, $x1, $y1);

        $r = (int) (
            (($c00 >> 16) & 0xFF) * $rfx * $rfy +
            (($c10 >> 16) & 0xFF) * $fx * $rfy +
            (($c01 >> 16) & 0xFF) * $rfx * $fy +
            (($c11 >> 16) & 0xFF) * $fx * $fy
        );
        $g = (int) (
            (($c00 >> 8) & 0xFF) * $rfx * $rfy +
            (($c10 >> 8) & 0xFF) * $fx * $rfy +
            (($c01 >> 8) & 0xFF) * $rfx * $fy +
            (($c11 >> 8) & 0xFF) * $fx * $fy
        );
        $b = (int) (
            ($c00 & 0xFF) * $rfx * $rfy +
            ($c10 & 0xFF) * $fx * $rfy +
            ($c01 & 0xFF) * $rfx * $fy +
            ($c11 & 0xFF) * $fx * $fy
        );

        return ($r << 16) | ($g << 8) | $b;
    }
}
