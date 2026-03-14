<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PriceHeatmapController
{
    public function index(Request $request): JsonResponse
    {
        $cityId = $request->query('city_id');
        $typeId = $request->query('type_id');

        $cacheKey = 'price_heatmap_'.md5($cityId.$typeId);

        $data = Cache::remember($cacheKey, 1800, function () use ($cityId, $typeId): array {
            $query = DB::table('ad')
                ->join('quarter', 'ad.quarter_id', '=', 'quarter.id')
                ->join('cities', 'quarter.city_id', '=', 'cities.id')
                ->whereNotNull('ad.price')
                ->where('ad.price', '>', 0)
                ->where('ad.status', AdStatus::AVAILABLE->value)
                ->whereNull('ad.deleted_at')
                ->selectRaw('
                    quarter.id as quarter_id,
                    quarter.name as quarter_name,
                    cities.id as city_id,
                    cities.name as city_name,
                    COUNT(*) as ad_count,
                    ROUND(AVG(ad.price)) as avg_price,
                    ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY ad.price)) as median_price,
                    ROUND(MIN(ad.price)) as min_price,
                    ROUND(MAX(ad.price)) as max_price
                ')
                ->groupBy('quarter.id', 'quarter.name', 'cities.id', 'cities.name')
                ->having(DB::raw('COUNT(*)'), '>=', 2);

            if ($cityId) {
                $query->where('quarter.city_id', $cityId);
            }

            if ($typeId) {
                $query->where('ad.type_id', $typeId);
            }

            $rows = $query->get();

            if ($rows->isEmpty()) {
                return ['features' => [], 'price_range' => ['min' => 0, 'max' => 0]];
            }

            $prices = $rows->pluck('median_price')->filter()->values();
            $globalMin = $prices->min();
            $globalMax = $prices->max();

            // Get quarter centroids from the quarter table
            $quarterIds = $rows->pluck('quarter_id');
            $centroids = DB::table('quarter')
                ->whereIn('id', $quarterIds)
                ->select('id', DB::raw('ST_Y(centroid::geometry) as lat'), DB::raw('ST_X(centroid::geometry) as lng'))
                ->get()
                ->keyBy('id');

            $features = $rows->map(function ($row) use ($centroids, $globalMin, $globalMax) {
                $centroid = $centroids->get($row->quarter_id);

                // Normalize price 0–1 for heatmap intensity
                $range = $globalMax - $globalMin;
                $intensity = $range > 0
                    ? ($row->median_price - $globalMin) / $range
                    : 0.5;

                return [
                    'quarter_id' => $row->quarter_id,
                    'quarter_name' => $row->quarter_name,
                    'city_name' => $row->city_name,
                    'lat' => $centroid?->lat ?? null,
                    'lng' => $centroid?->lng ?? null,
                    'ad_count' => (int) $row->ad_count,
                    'avg_price' => (int) $row->avg_price,
                    'median_price' => (int) $row->median_price,
                    'min_price' => (int) $row->min_price,
                    'max_price' => (int) $row->max_price,
                    'intensity' => round($intensity, 3),
                ];
            })->filter(fn ($f) => $f['lat'] !== null)->values()->all();

            return [
                'features' => $features,
                'price_range' => ['min' => (int) $globalMin, 'max' => (int) $globalMax],
            ];
        });

        return response()->json($data);
    }
}
