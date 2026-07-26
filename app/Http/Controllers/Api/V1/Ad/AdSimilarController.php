<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Http\Resources\AdResource;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Scout\Builder;
use Meilisearch\Endpoints\Indexes;
use OpenApi\Attributes as OA;

/**
 * Returns ads similar to a given ad.
 *
 * Strategy (roadmap 3.3):
 *   Same transaction_type + type_id + city, price within ±30%, excluding the ad itself.
 *   Sorted by Meilisearch relevance_score desc.
 *   Max 6 results.
 *
 * GET /api/v1/ads/{ad}/similar
 */
#[OA\Get(
    path: '/api/v1/ads/{ad}/similar',
    summary: 'Annonces similaires',
    description: 'Retourne jusqu\'à 6 annonces similaires (même type + ville + fourchette de prix ±30%). Triées par score de pertinence Meilisearch.',
    tags: ['🏠 Annonces'],
    parameters: [
        new OA\Parameter(name: 'ad', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste d\'annonces similaires'),
        new OA\Response(response: 404, description: 'Annonce introuvable'),
    ]
)]
final class AdSimilarController
{
    private const int LIMIT = 6;

    private const float PRICE_TOLERANCE = 0.30;

    public function __invoke(Request $request, Ad $ad): JsonResponse
    {
        $price = (float) ($ad->price ?? 0);
        $typeId = $ad->type_id;
        $cityId = $ad->quarter?->city_id;
        $txType = $ad->transaction_type;

        $publicStatuses = array_map(
            fn ($s) => '"'.$s->value.'"',
            Ad::PUBLIC_STATUSES,
        );
        $filters = [
            'status IN ['.implode(', ', $publicStatuses).']',
            'id != "'.$ad->id.'"',
        ];

        if ($txType) {
            $filters[] = 'transaction_type = "'.$txType->value.'"';
        }
        if ($typeId) {
            $filters[] = 'type_id = "'.$typeId.'"';
        }
        if ($cityId) {
            $filters[] = 'city_id = "'.$cityId.'"';
        }
        if ($price > 0) {
            $priceMin = round($price * (1 - self::PRICE_TOLERANCE));
            $priceMax = round($price * (1 + self::PRICE_TOLERANCE));
            $filters[] = "price >= {$priceMin} AND price <= {$priceMax}";
        }

        $filterString = implode(' AND ', $filters);

        try {
            /** @var Builder $builder */
            $builder = Ad::search('', function (Indexes $index, string $query, array $options) use ($filterString) {
                $options['filter'] = $filterString;
                $options['sort'] = ['desc(relevance_score)'];
                $options['limit'] = self::LIMIT;
                $options['attributesToRetrieve'] = ['id'];

                return $index->search($query, $options);
            });

            $results = $builder->get();
        } catch (\Throwable) {
            $results = collect();
        }

        if ($results->isEmpty()) {
            // Fallback: DB query without price constraint (loosen the search)
            $results = Ad::query()
                ->visible()
                ->whereIn('status', Ad::PUBLIC_STATUSES)
                ->where('id', '!=', $ad->id)
                ->when($txType, fn ($q) => $q->where('transaction_type', $txType))
                ->when($typeId, fn ($q) => $q->where('type_id', $typeId))
                ->whereHas('quarter', fn ($q) => $q->where('city_id', $cityId))
                ->orderByDesc('created_at')
                ->limit(self::LIMIT)
                ->with(['quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'user.media', 'user.latestTrustScore', 'agency'])
                ->get();

            return response()->json([
                'data' => AdResource::collection($results),
                'source' => 'fallback',
            ]);
        }

        // Load full models for the resource
        $ids = $results->pluck('id');
        $fullAds = Ad::with(['quarter.city', 'ad_type', 'media'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = $ids->map(fn ($id) => $fullAds->get($id))->filter();

        return response()->json([
            'data' => AdResource::collection($ordered),
            'source' => 'meilisearch',
        ]);
    }
}
