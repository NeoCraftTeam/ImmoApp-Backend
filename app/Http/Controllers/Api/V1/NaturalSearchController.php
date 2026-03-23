<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\AiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parses a natural language query into structured search parameters.
 *
 * Uses Groq LLM when configured (GROQ_API_KEY). Falls back to regex-based parsing otherwise.
 * Results are cached for 24 hours per query.
 *
 * Example: "appartement meublé 2 chambres à Douala pas cher avec parking"
 * Returns: { type_name: "Appartement", city_name: "Douala", bedrooms: 2, price_max: 100000, has_parking: true }
 */
final readonly class NaturalSearchController
{
    public function __construct(
        private AiSearchService $aiSearchService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/ads/search/natural",
     *     summary="Recherche en langage naturel",
     *     description="Parse une requête en langage naturel en paramètres de recherche structurés via IA.",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *             required={"q"},
     *
     *             @OA\Property(property="q", type="string", maxLength=300, example="appartement meublé 2 chambres à Douala")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Paramètres de recherche structurés"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function parse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:300'],
        ]);

        $query = trim((string) $data['q']);
        $result = $this->aiSearchService->parse($query);

        return response()->json($result);
    }
}
