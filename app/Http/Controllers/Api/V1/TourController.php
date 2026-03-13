<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\User;
use App\Services\TourService;
use App\Support\TourAssetToken;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class TourController
{
    use AuthorizesRequests;

    public function __construct(private TourService $tourService) {}

    /**
     * GET /api/v1/ads/{ad}/tour
     * Public — returns the tour config so the customer viewer can render it.
     */
    public function show(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('view', $ad);

        if (!$ad->has_3d_tour || !$ad->tour_config) {
            return response()->json(['message' => 'Aucun tour 3D pour cette annonce.'], 404);
        }

        if (!$this->canAccessTourAssets($request, $ad)) {
            return response()->json(['message' => 'Accès au tour 3D refusé.'], 403);
        }

        $signedConfig = $this->signTourConfigUrls((string) $ad->id, $ad->tour_config);

        return response()->json([
            'has_tour' => true,
            'scenes_count' => $ad->tour_scenes_count,
            'tour_published_at' => $ad->tour_published_at,
            'config' => $signedConfig,
        ]);
    }

    /**
     * POST /api/v1/ads/{ad}/tour/scenes
     * Owner only — upload 360° scene images and publish the tour.
     */
    public function uploadScenes(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        $request->validate([
            'scenes' => ['required', 'array', 'min:1', 'max:20'],
            'scenes.*.title' => ['required', 'string', 'max:50'],
            'scenes.*.image' => ['required', 'file', 'mimes:jpg,jpeg,webp', 'max:30720'],
            'scenes.*.hotspots' => ['nullable', 'array'],
            'scenes.*.hotspots.*.pitch' => ['required_with:scenes.*.hotspots', 'numeric', 'between:-90,90'],
            'scenes.*.hotspots.*.yaw' => ['required_with:scenes.*.hotspots', 'numeric', 'between:-180,180'],
            'scenes.*.hotspots.*.target_scene' => ['required_with:scenes.*.hotspots', 'string'],
            'scenes.*.hotspots.*.label' => ['required_with:scenes.*.hotspots', 'string', 'max:40'],
        ]);

        $uploadedScenes = [];

        foreach ($request->file('scenes') as $i => $sceneData) {
            $scene = $this->tourService->uploadScene(
                $ad,
                $sceneData['image'],
                (string) $request->input("scenes.{$i}.title")
            );
            $scene['hotspots'] = $request->input("scenes.{$i}.hotspots", []);
            $uploadedScenes[] = $scene;
        }

        $this->tourService->saveTourConfig($ad, $uploadedScenes);

        return response()->json([
            'message' => 'Tour 3D publié avec succès !',
            'scenes_count' => count($uploadedScenes),
            'config' => $ad->fresh()->tour_config,
        ], 201);
    }

    /**
     * PATCH /api/v1/ads/{ad}/tour/scenes/{sceneId}/hotspots
     * Owner only — update hotspots for one scene.
     */
    public function updateHotspots(Request $request, Ad $ad, string $sceneId): JsonResponse
    {
        $this->authorize('update', $ad);

        $sceneIds = collect($ad->tour_config['scenes'] ?? [])
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        $request->validate([
            'hotspots' => ['present', 'array', 'max:50'],
            'hotspots.*.pitch' => ['required', 'numeric', 'between:-90,90'],
            'hotspots.*.yaw' => ['required', 'numeric', 'between:-180,180'],
            'hotspots.*.target_scene' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($sceneIds, $sceneId): void {
                    if (!is_string($value) || !in_array($value, $sceneIds, true)) {
                        $fail('La scène de destination est invalide.');
                    }
                    if ($value === $sceneId) {
                        $fail('Un hotspot ne peut pas pointer vers sa propre scène.');
                    }
                },
            ],
            'hotspots.*.label' => ['required', 'string', 'max:40'],
        ]);

        $this->tourService->updateHotspots($ad, $sceneId, $request->input('hotspots'));

        return response()->json(['message' => 'Hotspots mis à jour.']);
    }

    /**
     * DELETE /api/v1/ads/{ad}/tour
     * Owner only — delete all scenes from S3 and reset tour fields.
     */
    public function destroy(Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);
        $this->tourService->deleteTour($ad);

        return response()->json(['message' => 'Tour 3D supprimé.']);
    }

    private function canAccessTourAssets(Request $request, Ad $ad): bool
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($ad->isUnlockedFor($user)) {
            return true;
        }

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @param  array<string, mixed>  $tourConfig
     * @return array<string, mixed>
     */
    private function signTourConfigUrls(string $adId, array $tourConfig): array
    {
        return TourAssetToken::signTourConfig($adId, $tourConfig);
    }
}
