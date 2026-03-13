<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TourService
{
    private const int EXIFTOOL_TIMEOUT_SECONDS = 10;

    /**
     * Extract GPano XMP metadata from a panoramic image file.
     *
     * Uses exiftool to read the standard Google Photo Sphere (GPano) metadata
     * and computes haov / vaov / vOffset for Pannellum.
     *
     * @return array{haov: float, vaov: float, vOffset: float, is_partial: bool}
     */
    public function extractPanoMetadata(string $filePath): array
    {
        $defaults = ['haov' => 360.0, 'vaov' => 180.0, 'vOffset' => 0.0, 'is_partial' => false];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return $defaults;
        }

        $exiftoolBin = $this->resolveExiftoolBinary();
        if ($exiftoolBin === null) {
            Log::info('TourService: exiftool not available, using default pano metadata.');

            return $this->estimateFromDimensions($filePath);
        }

        try {
            $result = Process::timeout(self::EXIFTOOL_TIMEOUT_SECONDS)
                ->run([$exiftoolBin, '-json', '-GPano:all', $filePath]);

            if (!$result->successful()) {
                Log::warning('TourService: exiftool failed', ['stderr' => $result->errorOutput()]);

                return $this->estimateFromDimensions($filePath);
            }

            /** @var array<int, array<string, mixed>> $parsed */
            $parsed = json_decode($result->output(), true);
            $data = $parsed[0] ?? [];

            $fullWidth = (int) ($data['FullPanoWidthPixels'] ?? 0);
            $fullHeight = (int) ($data['FullPanoHeightPixels'] ?? 0);
            $cropWidth = (int) ($data['CroppedAreaImageWidthPixels'] ?? 0);
            $cropHeight = (int) ($data['CroppedAreaImageHeightPixels'] ?? 0);
            $cropTop = (int) ($data['CroppedAreaTopPixels'] ?? 0);

            if ($fullWidth <= 0 || $fullHeight <= 0) {
                return $this->estimateFromDimensions($filePath);
            }

            $haov = ($cropWidth > 0 ? $cropWidth : $fullWidth) / $fullWidth * 360;
            $vaov = ($cropHeight > 0 ? $cropHeight : $fullHeight) / $fullHeight * 180;
            $vOffset = $cropHeight > 0
                ? (($cropTop + $cropHeight / 2) / $fullHeight - 0.5) * -180
                : 0.0;

            $isPartial = $haov < 359 || $vaov < 179;

            return [
                'haov' => round($haov, 4),
                'vaov' => round($vaov, 4),
                'vOffset' => round($vOffset, 4),
                'is_partial' => $isPartial,
            ];
        } catch (\Throwable $e) {
            Log::warning('TourService: exiftool exception', ['error' => $e->getMessage()]);

            return $this->estimateFromDimensions($filePath);
        }
    }

    /**
     * Heuristic fallback: estimate panorama coverage from image aspect ratio.
     *
     * A true equirectangular 360×180 image has a 2:1 aspect ratio.
     * iPhone panoramas are typically very wide (~7:1) and only cover ~50° vertically.
     *
     * @return array{haov: float, vaov: float, vOffset: float, is_partial: bool}
     */
    private function estimateFromDimensions(string $filePath): array
    {
        $defaults = ['haov' => 360.0, 'vaov' => 180.0, 'vOffset' => 0.0, 'is_partial' => false];

        $size = @getimagesize($filePath);
        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return $defaults;
        }

        [$width, $height] = $size;
        $ratio = $width / $height;

        if ($ratio >= 1.8 && $ratio <= 2.2) {
            return $defaults;
        }

        if ($ratio > 2.2) {
            $fullHeight = (int) ($width / 2);
            $vaov = $height / $fullHeight * 180;
            $vOffset = 0.0;

            return [
                'haov' => 360.0,
                'vaov' => round($vaov, 4),
                'vOffset' => round($vOffset, 4),
                'is_partial' => true,
            ];
        }

        return $defaults;
    }

    /**
     * Locate the exiftool binary, returning null if unavailable.
     */
    private function resolveExiftoolBinary(): ?string
    {
        $configPath = config('services.exiftool.path');
        if (is_string($configPath) && $configPath !== '' && is_executable($configPath)) {
            return $configPath;
        }

        foreach (['/usr/bin/exiftool', '/usr/local/bin/exiftool', '/opt/homebrew/bin/exiftool'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Upload one 360° scene image to S3 and return its scene descriptor array.
     *
     * @return array{id: string, title: string, image_url: string, initial_view: array{pitch: int, yaw: int, hfov: int}, hotspots: array<int, mixed>, haov: float, vaov: float, vOffset: float, is_partial_pano: bool}
     */
    public function uploadScene(Ad $ad, UploadedFile $file, string $sceneTitle): array
    {
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/webp'], true)) {
            throw new \InvalidArgumentException("Format non supporté : {$mime}. Utilise JPG ou WEBP.");
        }

        if ($file->getSize() === 0 || @getimagesize($file->getRealPath()) === false) {
            throw new \InvalidArgumentException('Le fichier image est vide ou corrompu.');
        }

        $panoMeta = $this->extractPanoMetadata($file->getRealPath());

        $slug = Str::slug($sceneTitle) ?: 'scene-'.Str::random(6);
        $filename = "{$slug}-".time().'.'.$file->getClientOriginalExtension();
        $path = "ads/{$ad->id}/tours/{$filename}";

        Storage::disk()->put($path, file_get_contents($file->getRealPath()));

        return [
            'id' => 'scene_'.Str::slug($sceneTitle).'_'.Str::random(4),
            'title' => $sceneTitle,
            'image_url' => route('tour.image.proxy', ['adId' => $ad->id, 'path' => $filename]),
            'image_path' => $path,
            'type' => 'equirectangular',
            'initial_view' => ['pitch' => 0, 'yaw' => 0, 'hfov' => 110],
            'hotspots' => [],
            'haov' => $panoMeta['haov'],
            'vaov' => $panoMeta['vaov'],
            'vOffset' => $panoMeta['vOffset'],
            'is_partial_pano' => $panoMeta['is_partial'],
        ];
    }

    /**
     * Persist the full tour config (scenes array) to the ad.
     *
     * @param  array<int, mixed>  $scenes
     */
    public function saveTourConfig(Ad $ad, array $scenes): void
    {
        $ad->update([
            'has_3d_tour' => true,
            'tour_config' => [
                'default_scene' => $scenes[0]['id'] ?? null,
                'scenes' => $scenes,
            ],
            'tour_published_at' => now(),
        ]);
    }

    /**
     * Delete all S3 scene images and reset the tour fields on the ad.
     */
    public function deleteTour(Ad $ad): void
    {
        if (!$ad->tour_config) {
            return;
        }

        Storage::disk()->deleteDirectory("ads/{$ad->id}/tours");
        Storage::disk()->deleteDirectory("tours/{$ad->id}");

        $ad->update([
            'has_3d_tour' => false,
            'tour_config' => null,
            'tour_published_at' => null,
        ]);
    }

    /**
     * Replace the hotspots of one scene in the stored config.
     *
     * @param  array<int, mixed>  $hotspots
     */
    public function updateHotspots(Ad $ad, string $sceneId, array $hotspots): void
    {
        DB::transaction(function () use ($ad, $sceneId, $hotspots): void {
            /** @var Ad|null $lockedAd */
            $lockedAd = Ad::query()
                ->whereKey($ad->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedAd) {
                throw ValidationException::withMessages([
                    'ad' => 'Annonce introuvable.',
                ]);
            }

            $config = $lockedAd->tour_config ?? [];
            $scenes = array_values($config['scenes'] ?? []);

            $sceneIds = collect($scenes)
                ->pluck('id')
                ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                ->values()
                ->all();

            if (!in_array($sceneId, $sceneIds, true)) {
                throw ValidationException::withMessages([
                    'scene_id' => 'La scène à modifier est introuvable dans ce tour.',
                ]);
            }

            foreach ($hotspots as $index => $hotspot) {
                $targetScene = (string) ($hotspot['target_scene'] ?? '');
                if (!in_array($targetScene, $sceneIds, true)) {
                    throw ValidationException::withMessages([
                        "hotspots.{$index}.target_scene" => 'La scène de destination est invalide.',
                    ]);
                }
            }

            $hasBeenUpdated = false;
            $config['scenes'] = array_map(function (array $scene) use ($sceneId, $hotspots, &$hasBeenUpdated): array {
                if (($scene['id'] ?? null) === $sceneId) {
                    $scene['hotspots'] = $hotspots;
                    $hasBeenUpdated = true;
                }

                return $scene;
            }, $scenes);

            if (!$hasBeenUpdated) {
                throw ValidationException::withMessages([
                    'scene_id' => 'Impossible de mettre à jour les hotspots de cette scène.',
                ]);
            }

            $lockedAd->update(['tour_config' => $config]);
        }, attempts: 3);
    }
}
