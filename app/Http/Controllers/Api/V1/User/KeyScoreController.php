<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Models\Ad;
use App\Models\AdInteraction;
use App\Services\Trust\KeyScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

final class KeyScoreController
{
    /**
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/keyscore",
     *     summary="Score de qualité d'une annonce",
     *     description="Retourne le KeyScore (score de qualité) d'une annonce avec le détail des critères.",
     *     tags={"🏠 Annonces"},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="KeyScore calculé"),
     *     @OA\Response(response=404, description="Annonce introuvable")
     * )
     */
    public function show(Ad $ad, KeyScoreService $service): JsonResponse
    {
        $cacheKey = 'keyscore_'.$ad->id.'_'.now()->format('Ymd_H');

        $result = Cache::remember($cacheKey, 3600, function () use ($ad, $service) {
            $ad->loadCount(['interactions as views_count' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW)]);

            return $service->compute($ad);
        });

        return response()->json($result);
    }
}
