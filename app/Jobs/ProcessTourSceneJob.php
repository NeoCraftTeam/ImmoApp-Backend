<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ad;
use App\Services\PanoramaProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessTourSceneJob implements ShouldQueue
{
    use Queueable;

    /** Allow up to 15 minutes — pixel-loop projection is CPU-intensive in PHP. */
    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(
        public readonly string $adId,
        public readonly string $sceneId,
        public readonly string $panoramaType,
        public readonly string $sourceR2Path,
    ) {}

    public function handle(PanoramaProcessor $processor): void
    {
        $ad = Ad::find($this->adId);
        if (!$ad) {
            Log::warning('ProcessTourSceneJob: Ad not found', ['adId' => $this->adId]);

            return;
        }

        $faceSize = $this->panoramaType === 'multires' ? 2048 : 1024;
        $facesPrefix = "ads/{$this->adId}/tours/scenes/{$this->sceneId}/faces";
        $tilesPrefix = "ads/{$this->adId}/tours/scenes/{$this->sceneId}/tiles";
        $fallbackPrefix = "ads/{$this->adId}/tours/scenes/{$this->sceneId}/fallback";

        // Step 1 — equirectangular → 6 cube faces (the slow pixel-loop step)
        $facePaths = $processor->generateCubeFaces($this->sourceR2Path, $facesPrefix, $faceSize);

        $updates = ['processing' => false];

        if ($this->panoramaType === 'cubemap') {
            // Build proxy URLs for each face in Pannellum's expected order: f, r, b, l, u, d
            $updates['cube_map'] = array_map(
                $this->proxyUrl(...),
                array_values($facePaths),
            );
        } else {
            // multires — generate tile pyramid and low-res fallback faces
            $tileSize = 512;
            $cubeResolution = 2048;

            $processor->generateTilePyramid($facePaths, $tilesPrefix, $tileSize, $cubeResolution);
            $processor->generateFallbackFaces($facePaths, $fallbackPrefix);

            $maxLevel = (int) ceil(log($cubeResolution / $tileSize, 2)) + 1;

            $updates['tiles_base_url'] = $this->proxyUrl("ads/{$this->adId}/tours/scenes/{$this->sceneId}/tiles");
            $updates['fallback_base_url'] = $this->proxyUrl("ads/{$this->adId}/tours/scenes/{$this->sceneId}/fallback");
            $updates['tiles_max_level'] = $maxLevel;
            $updates['cube_resolution'] = $cubeResolution;
        }

        // Atomically patch this scene inside tour_config
        $this->patchScene($ad, $updates);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessTourSceneJob failed', [
            'adId' => $this->adId,
            'sceneId' => $this->sceneId,
            'type' => $this->panoramaType,
            'error' => $e->getMessage(),
        ]);

        $ad = Ad::find($this->adId);
        if ($ad) {
            $this->patchScene($ad, ['processing' => false, 'processing_failed' => true]);
        }

        Storage::disk()->deleteDirectory("ads/{$this->adId}/tours/scenes/{$this->sceneId}");
    }

    /**
     * Build the public proxy URL for a given R2 path.
     * Strips the leading "ads/{adId}/tours/" prefix and delegates to the proxy route.
     */
    private function proxyUrl(string $r2Path): string
    {
        // r2Path format: ads/{adId}/tours/...rest
        $rest = (string) preg_replace('#^ads/[^/]+/tours/#', '', $r2Path);

        return route('tour.image.proxy', ['adId' => $this->adId, 'path' => $rest]);
    }

    /**
     * Merge $updates into the scene identified by $this->sceneId inside Ad::tour_config.
     */
    private function patchScene(Ad $ad, array $updates): void
    {
        DB::transaction(function () use ($ad, $updates): void {
            /** @var Ad|null $lockedAd */
            $lockedAd = Ad::query()
                ->whereKey($ad->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedAd) {
                return;
            }

            $config = $lockedAd->tour_config ?? [];
            $scenes = collect($config['scenes'] ?? [])
                ->map(fn (array $scene) => ($scene['id'] ?? null) === $this->sceneId
                    ? array_merge($scene, $updates)
                    : $scene
                )
                ->values()
                ->toArray();

            $lockedAd->update([
                'tour_config' => array_merge($config, ['scenes' => $scenes]),
            ]);
        }, attempts: 3);
    }
}
