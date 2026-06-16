<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Geo;

use App\Enums\TransactionType;
use App\Models\Ad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class RentEstimatorController
{
    private const string CACHE_VERSION = 'v3';

    /** Plausible monthly rent per m² (FCFA) — reduces pollution from vente prices or bad data */
    private const float PPSM_MIN = 50.0;

    private const float PPSM_MAX = 30_000.0;

    /**
     * Below this number of comparables the percentile estimate is more
     * a coin-flip than a forecast — a single luxury villa in an otherwise-
     * cheap quarter would skew p75 by 5×. Flag the response so the
     * frontend can render an "estimation indicative" disclaimer and avoid
     * presenting a min/max range that doesn't reflect a real distribution.
     */
    private const int RELIABLE_SAMPLE_MIN = 5;

    /**
     * @OA\Post(
     *     path="/api/v1/ads/rent-estimate",
     *     summary="Estimer le loyer",
     *     description="Estime le loyer moyen pour un type de bien dans une ville donnée, basé sur les annonces existantes.",
     *     tags={"🏠 Annonces"},
     *
     *     @OA\RequestBody(required=true,
     *
     *         @OA\JsonContent(
     *             required={"city_id", "type_id", "surface"},
     *
     *             @OA\Property(property="city_id", type="string", format="uuid"),
     *             @OA\Property(property="type_id", type="string", format="uuid"),
     *             @OA\Property(property="surface", type="integer", minimum=10, maximum=10000),
     *             @OA\Property(property="bedrooms", type="integer", minimum=0, maximum=20, nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Estimation calculée"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city_id' => ['required', 'uuid'],
            'type_id' => ['required', 'uuid'],
            'surface' => ['required', 'integer', 'min:10', 'max:10000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $cacheKey = 'rent_estimate_'.self::CACHE_VERSION.'_'.md5(serialize($data));

        $result = Cache::remember($cacheKey, 3600, fn (): array => $this->computeEstimate($data));

        return response()->json($result);
    }

    /**
     * @param  array{city_id: string, type_id: string, surface: int, bedrooms?: int}  $data
     * @return array<string, mixed>
     */
    private function computeEstimate(array $data): array
    {
        $typeScopeMatched = true;
        $bedroomsScopeMatched = !isset($data['bedrooms']);

        $query = $this->scopedForCityAndType($data['city_id'], $data['type_id']);
        if (isset($data['bedrooms'])) {
            $query->where('bedrooms', $data['bedrooms']);
        }

        $pricePerSqm = $this->pluckSanitizedPpsm($query);

        if ($pricePerSqm->count() < 3 && isset($data['bedrooms'])) {
            $bedroomsScopeMatched = false;
            $queryNoBed = $this->scopedForCityAndType($data['city_id'], $data['type_id']);
            $pricePerSqm = $this->pluckSanitizedPpsm($queryNoBed);
        }

        if ($pricePerSqm->isEmpty()) {
            $typeScopeMatched = false;
            $queryCityOnly = $this->scopedForCity($data['city_id']);
            $pricePerSqm = $this->pluckSanitizedPpsm($queryCityOnly);
        }

        if ($pricePerSqm->isEmpty()) {
            return ['error' => 'Pas assez de données pour cette ville.'];
        }

        $sorted = $pricePerSqm->sort()->values();
        $count = $sorted->count();
        $midIdx = max(0, (int) floor($count * 0.50));
        $p25 = $sorted[max(0, (int) floor($count * 0.25))];
        $p50 = $sorted[$midIdx];
        $p75 = $sorted[min($count - 1, (int) floor($count * 0.75))];
        $estimatedMin = (int) round($p25 * $data['surface']);
        $estimatedMedian = (int) round($p50 * $data['surface']);
        $estimatedMax = (int) round($p75 * $data['surface']);

        return [
            'estimated_min' => $estimatedMin,
            'estimated_median' => $estimatedMedian,
            'estimated_max' => $estimatedMax,
            'price_per_sqm' => [
                'p25' => round($p25),
                'p50' => round($p50),
                'p75' => round($p75),
            ],
            'sample_count' => $count,
            // True when the percentile estimate stands on a thin enough
            // sample that the frontend should show a "indicative" hint
            // instead of presenting it as a confident forecast.
            'is_unreliable' => $count < self::RELIABLE_SAMPLE_MIN,
            'surface' => $data['surface'],
            'type_scope_matched' => $typeScopeMatched,
            'bedrooms_scope_matched' => $bedroomsScopeMatched,
        ];
    }

    private function scopedForCity(string $cityId): Builder
    {
        return $this->rentLikeBaseQuery($cityId);
    }

    private function scopedForCityAndType(string $cityId, string $typeId): Builder
    {
        return $this->rentLikeBaseQuery($cityId)
            ->where('type_id', $typeId);
    }

    private function rentLikeBaseQuery(string $cityId): Builder
    {
        return Ad::query()
            ->whereHas('quarter', fn ($q) => $q->where('city_id', $cityId))
            ->visible()
            ->publiclyListed()
            ->where(function ($q): void {
                $q->where('transaction_type', TransactionType::LOCATION)
                    ->orWhereNull('transaction_type');
            })
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->where('surface_area', '>', 0);
    }

    private function pluckSanitizedPpsm(Builder $query): Collection
    {
        $raw = $query->clone()
            ->selectRaw('price / NULLIF(surface_area, 0) as ppsm')
            ->pluck('ppsm');

        return $raw
            ->map(fn ($v) => is_numeric($v) ? (float) $v : null)
            ->filter()
            ->filter(fn (float $v): bool => $v >= self::PPSM_MIN && $v <= self::PPSM_MAX)
            ->values();
    }
}
