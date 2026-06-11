<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AiSearchServiceInterface;
use App\Http\Requests\Api\V1\NaturalSearchParseRequest;
use App\Models\NlpSearchLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Parses a natural language query into structured search parameters.
 *
 * Delegates to AiSearchService which tries configured LLM providers in order
 * (AI_SEARCH_PROVIDERS env: groq, openai, gemini, together, mistral).
 * Falls back to regex-based parsing when all providers fail or are unconfigured.
 * Results are cached for 24 hours per (query, currency, context).
 *
 * Customer surface example: "appartement meublé 2 chambres à Douala pas cher avec parking"
 * → { type_name: "Appartement", city_name: "Douala", bedrooms: 2, price_max: 100000, has_parking: true }
 *
 * Owner surface (owner_context=true, requires AGENT/ADMIN auth):
 *   "mes annonces à Douala non boostées avec moins de 50 vues"
 * → { city_name: "Douala", boost_status: "not_boosted", views_max: 50 }
 */
final readonly class NaturalSearchController
{
    public function __construct(
        private AiSearchServiceInterface $aiSearchService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/ads/search/parse",
     *     summary="Recherche en langage naturel",
     *     description="Parse une requête en langage naturel en paramètres de recherche structurés via IA. Le drapeau owner_context active la surface bailleur (auth AGENT/ADMIN requise).",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *             required={"q"},
     *
     *             @OA\Property(property="q", type="string", maxLength=300, example="appartement meublé 2 chambres à Douala"),
     *             @OA\Property(property="display_currency", type="string", example="EUR"),
     *             @OA\Property(property="owner_context", type="boolean", example=false)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Paramètres de recherche structurés"),
     *     @OA\Response(response=403, description="owner_context demandé sans rôle AGENT/ADMIN"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function parse(NaturalSearchParseRequest $request): JsonResponse
    {
        $query = $request->normalisedQuery();
        $displayCurrency = $request->normalisedDisplayCurrency();
        $context = $request->context();

        $start = microtime(true);
        $result = $this->aiSearchService->parse($query, $displayCurrency, $context);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        $this->logSearch($request, $query, $displayCurrency, $context, $result, $latencyMs);

        return response()->json($result);
    }

    /**
     * Best-effort write to the nlp_search_logs table. Failures are swallowed —
     * we never let logging break a user-facing search.
     *
     * @param  array<string, mixed>  $result
     */
    private function logSearch(
        NaturalSearchParseRequest $request,
        string $query,
        ?string $displayCurrency,
        string $context,
        array $result,
        int $latencyMs,
    ): void {
        try {
            $user = $request->user();

            NlpSearchLog::create([
                'user_id' => $user instanceof User ? $user->id : null,
                'ip' => $request->ip(),
                'context' => $context,
                'query' => mb_substr($query, 0, 320),
                'display_currency' => $displayCurrency,
                'success_provider' => $this->resolveSuccessProvider($result),
                'parsed' => $result,
                'latency_ms' => $latencyMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('NaturalSearchController: failed to log NLP search: '.$e->getMessage());
        }
    }

    /**
     * Detect which provider answered. The service exposes its outcome via the
     * Sentry breadcrumb, not the return shape — we approximate from structured
     * fields here. "regex" is set when nothing structured came back AND `q`
     * mirrors the raw query (regex fallback's signature). Anything else is
     * assumed to come from one of the LLM providers.
     *
     * @param  array<string, mixed>  $result
     */
    private function resolveSuccessProvider(array $result): string
    {
        $hasStructured = !empty($result['transaction_type'])
            || !empty($result['type_id'])
            || !empty($result['city_id'])
            || !empty($result['bedrooms'])
            || !empty($result['price_max'])
            || !empty($result['price_min'])
            || !empty($result['surface_min'])
            || !empty($result['has_parking'])
            || !empty($result['furnished'])
            || !empty($result['status'] ?? null)
            || !empty($result['boost_status'] ?? null)
            || ($result['is_visible'] ?? null) !== null
            || ($result['views_min'] ?? null) !== null
            || ($result['views_max'] ?? null) !== null;

        return $hasStructured ? 'llm' : 'regex';
    }
}
