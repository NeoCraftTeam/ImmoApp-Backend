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
