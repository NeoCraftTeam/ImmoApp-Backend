<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TourService
{
    /**
     * Upload one 360° scene image to S3 and return its scene descriptor array.
     *
     * @return array{id: string, title: string, image_url: string, initial_view: array{pitch: int, yaw: int, hfov: int}, hotspots: array<int, mixed>}
     */
    public function uploadScene(Ad $ad, UploadedFile $file, string $sceneTitle): array
    {
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/webp'], true)) {
            throw new \InvalidArgumentException("Format non supporté : {$mime}. Utilise JPG ou WEBP.");
        }

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

        foreach ($ad->tour_config['scenes'] ?? [] as $scene) {
            $path = $scene['image_path'] ?? null;

            if (!$path && isset($scene['image_url'])) {
                // Fallback for legacy configs: extract and correct path from proxy URL
                // /tour-image/uuid/filename.jpg -> tours/uuid/filename.jpg
                $urlPath = parse_url((string) $scene['image_url'], PHP_URL_PATH);
                if ($urlPath) {
                    $path = str_replace('tour-image/', 'tours/', ltrim($urlPath, '/'));
                }
            }

            if ($path) {
                Storage::disk()->delete($path);
            }
        }

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
        $config = $ad->tour_config;

        $config['scenes'] = array_map(function (array $scene) use ($sceneId, $hotspots): array {
            if ($scene['id'] === $sceneId) {
                $scene['hotspots'] = $hotspots;
            }

            return $scene;
        }, $config['scenes']);

        $ad->update(['tour_config' => $config]);
    }
}
