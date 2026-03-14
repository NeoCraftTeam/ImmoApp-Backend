<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class RentEstimatorController
{
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city_id' => ['required', 'uuid'],
            'type_id' => ['required', 'uuid'],
            'surface' => ['required', 'integer', 'min:10', 'max:10000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $cacheKey = 'rent_estimate_'.md5(serialize($data));

        $result = Cache::remember($cacheKey, 3600, function () use ($data): array {
            $query = Ad::query()
                ->whereHas('quarter', fn ($q) => $q->where('city_id', $data['city_id']))
                ->where('type_id', $data['type_id'])
                ->where('status', AdStatus::AVAILABLE)
                ->whereNotNull('price')
                ->where('price', '>', 0)
                ->where('surface_area', '>', 0);

            if (isset($data['bedrooms'])) {
                $query->where('bedrooms', $data['bedrooms']);
            }

            // Price per m² approach
            $pricePerSqm = $query->clone()
                ->selectRaw('price / NULLIF(surface_area, 0) as ppsm')
                ->pluck('ppsm')
                ->filter()
                ->values();

            if ($pricePerSqm->count() < 3) {
                // Fallback: ignore bedrooms filter
                $pricePerSqm = Ad::query()
                    ->whereHas('quarter', fn ($q) => $q->where('city_id', $data['city_id']))
                    ->where('type_id', $data['type_id'])
                    ->where('status', AdStatus::AVAILABLE)
                    ->whereNotNull('price')
                    ->where('price', '>', 0)
                    ->where('surface_area', '>', 0)
                    ->selectRaw('price / NULLIF(surface_area, 0) as ppsm')
                    ->pluck('ppsm')
                    ->filter()
                    ->values();
            }

            if ($pricePerSqm->isEmpty()) {
                return ['error' => 'Pas assez de données pour cette combinaison.'];
            }

            $sorted = $pricePerSqm->sort()->values();
            $count = $sorted->count();
            $p25 = $sorted[(int) floor($count * 0.25)];
            $p50 = $sorted[(int) floor($count * 0.50)];
            $p75 = $sorted[(int) floor($count * 0.75)];

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
                'surface' => $data['surface'],
            ];
        });

        return response()->json($result);
    }
}
