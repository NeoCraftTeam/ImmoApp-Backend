<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\User;
use App\Rules\VerifiedImageUpload;
use App\Services\TourService;
use App\Support\PanoramaAngles;
use App\Support\TourAssetToken;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class TourController
{
    use AuthorizesRequests;

    public function __construct(private TourService $tourService) {}

    /**
     * GET /api/v1/ads/{ad}/tour
     * Public — returns the tour config so the customer viewer can render it.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/tour",
     *     summary="Configuration du tour 3D",
     *     description="Retourne la configuration du tour 3D (scènes, URLs signées, hotspots) pour le viewer côté client. Accessible uniquement aux utilisateurs ayant débloqué l'annonce ou aux admins.",
     *     operationId="getTour",
     *     tags={"🏡 Tour 3D"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, description="UUID de l'annonce", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Configuration du tour 3D", @OA\JsonContent(
     *
     *         @OA\Property(property="has_tour", type="boolean"),
     *         @OA\Property(property="scenes_count", type="integer"),
     *         @OA\Property(property="tour_published_at", type="string", format="date-time", nullable=true),
     *         @OA\Property(property="config", type="object")
     *     )),
     *
     *     @OA\Response(response=403, description="Accès refusé (annonce non débloquée)"),
     *     @OA\Response(response=404, description="Aucun tour 3D pour cette annonce")
     * )
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

        $signedConfig = $this->signTourConfigUrls((string) $ad->id, TourAssetToken::normalizeHotspotsLists($ad->tour_config));

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
     *
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/tour/scenes",
     *     summary="Uploader les scènes 360° du tour",
     *     description="Upload des images équirectangulaires 360° (JPEG/WebP, max 30 Mo chacune). Configure les hotspots de navigation. Max 20 scènes par annonce.",
     *     operationId="uploadTourScenes",
     *     tags={"🏡 Tour 3D"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *
     *         @OA\Schema(
     *             required={"scenes"},
     *
     *             @OA\Property(property="scenes", type="array", maxItems=20,
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="image", type="string", format="binary"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="client_id", type="string", nullable=true),
     *                     @OA\Property(property="hotspots", type="array", nullable=true, @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     )),
     *
     *     @OA\Response(response=200, description="Tour créé / mis à jour"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Fichier invalide ou quota dépassé")
     * )
     */
    public function uploadScenes(Request $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);

        $this->normalizeTourSceneHotspotYawsInRequest($request);

        $request->validate([
            'scenes' => ['required', 'array', 'min:1', 'max:20'],
            'scenes.*.title' => ['required', 'string', 'max:50'],
            'scenes.*.image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,webp',
                'mimetypes:image/jpeg,image/webp',
                'max:30720',
                new VerifiedImageUpload,
            ],
            'scenes.*.client_id' => ['nullable', 'string', 'max:100'],
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

        // Remap hotspot target_scene values from client-side identifiers to the real
        // backend scene IDs assigned by uploadScene().
        //
        // Resolution priority:
        //   1. client_id  — exact match against the temp ID sent by the frontend (e.g. "new-1234567890")
        //   2. slug match — Str::slug($target) matches a scene title slug
        //   3. title match — case-insensitive exact title match
        $idByClientId = [];
        $idBySlug = [];
        $idByTitle = [];

        foreach ($request->input('scenes', []) as $i => $sceneInput) {
            $clientId = trim((string) ($sceneInput['client_id'] ?? ''));
            if ($clientId !== '' && isset($uploadedScenes[$i])) {
                $idByClientId[$clientId] = $uploadedScenes[$i]['id'];
            }
        }

        foreach ($uploadedScenes as $s) {
            $idBySlug[Str::slug($s['title'])] = $s['id'];
            $idByTitle[mb_strtolower(trim($s['title']))] = $s['id'];
        }
        $validIds = array_column($uploadedScenes, 'id');

        $uploadedScenes = array_map(function (array $scene) use ($validIds, $idByClientId, $idBySlug, $idByTitle): array {
            if (empty($scene['hotspots']) || !is_array($scene['hotspots'])) {
                return $scene;
            }

            $scene['hotspots'] = array_values(array_map(function (array $hotspot) use ($validIds, $idByClientId, $idBySlug, $idByTitle): array {
                $target = (string) ($hotspot['target_scene'] ?? '');
                if ($target === '' || in_array($target, $validIds, true)) {
                    return $hotspot;
                }

                // 1. Exact client_id match (temp ID from frontend e.g. "new-1234567890")
                if (isset($idByClientId[$target])) {
                    $hotspot['target_scene'] = $idByClientId[$target];

                    return $hotspot;
                }

                // 2. Title slug fallback
                $slug = Str::slug($target);
                if (isset($idBySlug[$slug])) {
                    $hotspot['target_scene'] = $idBySlug[$slug];
                } elseif (isset($idByTitle[mb_strtolower(trim($target))])) {
                    $hotspot['target_scene'] = $idByTitle[mb_strtolower(trim($target))];
                }

                return $hotspot;
            }, $scene['hotspots']));

            return $scene;
        }, $uploadedScenes);

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
     *
     * @OA\Patch(
     *     path="/api/v1/ads/{ad}/tour/scenes/{sceneId}/hotspots",
     *     summary="Mettre à jour les hotspots d'une scène",
     *     description="Remplace la liste des hotspots d'une scène du tour 3D (liens de navigation entre scènes).",
     *     operationId="updateTourHotspots",
     *     tags={"🏡 Tour 3D"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="sceneId", in="path", required=true, description="UUID de la scène", @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"hotspots"},
     *
     *         @OA\Property(property="hotspots", type="array", @OA\Items(
     *             type="object",
     *             @OA\Property(property="pitch", type="number"),
     *             @OA\Property(property="yaw", type="number"),
     *             @OA\Property(property="target_scene", type="string"),
     *             @OA\Property(property="label", type="string")
     *         ))
     *     )),
     *
     *     @OA\Response(response=200, description="Hotspots mis à jour"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Scène introuvable")
     * )
     */
    public function updateHotspots(Request $request, Ad $ad, string $sceneId): JsonResponse
    {
        $this->authorize('update', $ad);

        $this->normalizeHotspotListYawsInRequest($request);

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
     *
     * @OA\Delete(
     *     path="/api/v1/ads/{ad}/tour",
     *     summary="Supprimer le tour 3D",
     *     description="Supprime toutes les scènes du stockage (S3/R2) et réinitialise les champs du tour sur l'annonce.",
     *     operationId="destroyTour",
     *     tags={"🏡 Tour 3D"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Tour supprimé"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
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

    /**
     * Merge normalized yaw for each hotspot under scenes.*.hotspots.* (multipart upload).
     * Does not replace whole `scenes` entries so file uploads stay intact.
     */
    private function normalizeTourSceneHotspotYawsInRequest(Request $request): void
    {
        $scenes = $request->input('scenes');
        if (!is_array($scenes)) {
            return;
        }

        foreach (array_keys($scenes) as $i) {
            $hotspots = $request->input("scenes.{$i}.hotspots");
            if (!is_array($hotspots)) {
                continue;
            }

            foreach (array_keys($hotspots) as $j) {
                $yaw = $request->input("scenes.{$i}.hotspots.{$j}.yaw");
                if (!is_numeric($yaw)) {
                    continue;
                }

                $request->merge([
                    "scenes.{$i}.hotspots.{$j}.yaw" => PanoramaAngles::normalizeYawDegrees((float) $yaw),
                ]);
            }
        }
    }

    /**
     * Merge normalized yaw for hotspots.*.yaw (JSON / form body).
     */
    private function normalizeHotspotListYawsInRequest(Request $request): void
    {
        $hotspots = $request->input('hotspots');
        if (!is_array($hotspots)) {
            return;
        }

        foreach (array_keys($hotspots) as $j) {
            $yaw = $request->input("hotspots.{$j}.yaw");
            if (!is_numeric($yaw)) {
                continue;
            }

            $request->merge([
                "hotspots.{$j}.yaw" => PanoramaAngles::normalizeYawDegrees((float) $yaw),
            ]);
        }
    }
}
