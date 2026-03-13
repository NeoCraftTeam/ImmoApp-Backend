<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
