<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AiDescriptionEnhancer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdAiController
{
    /**
     * @OA\Post(
     *     path="/api/v1/ads/enhance-description",
     *     summary="Améliorer une description d'annonce via IA",
     *     description="Utilise l'IA pour améliorer la description d'une annonce immobilière.",
     *     tags={"🏠 Annonces"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *             required={"description"},
     *
     *             @OA\Property(property="description", type="string", maxLength=10000)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Description améliorée"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'max:10000'],
        ]);

        $enhanced = app(AiDescriptionEnhancer::class)->enhance($request->input('description'));

        return response()->json([
            'enhanced' => $enhanced,
        ]);
    }
}
