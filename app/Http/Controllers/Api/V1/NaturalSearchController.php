<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Ai\AiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parses a natural language query into structured search parameters.
 *
 * Delegates to AiSearchService which tries configured LLM providers in order
 * (AI_SEARCH_PROVIDERS env: groq, openai, gemini, together, mistral).
 * Falls back to regex-based parsing when all providers fail or are unconfigured.
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
            // ISO-4217 code from the visitor's CurrencyProvider. Used by the
            // LLM to interpret non-XAF amounts in the query (e.g. "200 EUR").
            'display_currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
        ]);

        $query = trim((string) $data['q']);
        $displayCurrency = isset($data['display_currency'])
            ? strtoupper((string) $data['display_currency'])
            : null;

        $result = $this->aiSearchService->parse($query, $displayCurrency);

        return response()->json($result);
    }
}
