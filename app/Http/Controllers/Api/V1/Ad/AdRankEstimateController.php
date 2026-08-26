<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Meilisearch\Client;
use OpenApi\Attributes as OA;

/**
 * Returns an estimated search rank for an ad within its market segment
 * (same transaction_type + type_id + city).
 *
 * Rank is computed by counting active ads in Meilisearch that have a
 * higher relevance_score than the subject ad.
 *
 * GET /api/v1/ads/{ad}/rank-estimate
 */
#[OA\Get(
    path: '/api/v1/ads/{ad}/rank-estimate',
    summary: 'Classement estimé dans les résultats de recherche',
    description: 'Retourne la position estimée d\'une annonce dans les résultats Meilisearch pour le même segment marché (ville + type + transaction). Le propriétaire voit son classement et le score de pertinence.',
    tags: ['🏠 Annonces'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'ad', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Rank data'),
        new OA\Response(response: 403, description: 'Non autorisé'),
        new OA\Response(response: 404, description: 'Annonce introuvable'),
    ]
)]
final class AdRankEstimateController
{
    public function __invoke(Request $request, Ad $ad): JsonResponse
    {
        // Only the ad owner (or admins) may see rank data
        if ($request->user()?->id !== $ad->user_id && !$request->user()?->isAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        if (!in_array($ad->status, Ad::PUBLIC_STATUSES, true)) {
            return response()->json([
                'rank' => null,
                'total_in_market' => 0,
                'relevance_score' => $ad->computeRelevanceScore(),
                'percentile' => null,
                'message' => 'L\'annonce n\'est pas encore publiée.',
            ]);
        }

        $score = $ad->computeRelevanceScore();

        // Build filter: same transaction_type + type_id (optional) + city
        $publicStatuses = array_map(
            fn ($s) => '"'.$s->value.'"',
            Ad::PUBLIC_STATUSES,
        );
        $filters = ['status IN ['.implode(', ', $publicStatuses).']'];

        if ($ad->transaction_type) {
            $filters[] = 'transaction_type = "'.$ad->transaction_type->value.'"';
        }
        if ($ad->type_id) {
            $filters[] = 'type_id = "'.$ad->type_id.'"';
        }

        // Filter by city via quarter relationship (use city_id from Meilisearch)
        $cityId = $ad->quarter?->city_id;
        if ($cityId) {
            $filters[] = 'city_id = "'.$cityId.'"';
        }

        $filterString = implode(' AND ', $filters);

        // Count ads with a higher score (= ads ranked above this one)
        $filtersWithHigherScore = $filterString.' AND relevance_score > '.$score;
        $filtersAll = $filterString;

        $higherCount = $this->countMeilisearch($filtersWithHigherScore);
        $totalCount = $this->countMeilisearch($filtersAll);

        $rank = $higherCount + 1;
        $percentile = $totalCount > 0 ? round((($totalCount - $rank + 1) / $totalCount) * 100) : null;

        return response()->json([
            'rank' => $rank,
            'total_in_market' => $totalCount,
            'relevance_score' => $score,
            'percentile' => $percentile,
            'city_id' => $cityId,
            'segment' => [
                'type' => $ad->ad_type?->name,
                'transaction_type' => $ad->transaction_type?->value,
                'city' => $ad->quarter?->city?->name,
            ],
        ]);
    }

    private function countMeilisearch(string $filterString): int
    {
        try {
            $msClient = new Client(
                (string) config('scout.meilisearch.host'),
                (string) config('scout.meilisearch.key'),
            );
            $index = $msClient->index((new Ad)->searchableAs());

            $result = $index->search('', [
                'filter' => $filterString,
                'limit' => 0,
                'attributesToRetrieve' => ['id'],
            ]);

            return $result->getEstimatedTotalHits() ?? 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
