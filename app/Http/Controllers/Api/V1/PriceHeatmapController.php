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

        $cacheKey = 'price_heatmap_'.md5((string) $cityId.(string) $typeId);

        $data = Cache::remember($cacheKey, 1800, function () use ($cityId, $typeId): array {
            $query = DB::table('ad')
                ->join('quarter', 'ad.quarter_id', '=', 'quarter.id')
                ->join('city', 'quarter.city_id', '=', 'city.id')
                ->whereNotNull('ad.price')
                ->where('ad.price', '>', 0)
                ->where('ad.status', AdStatus::AVAILABLE->value)
                ->whereNull('ad.deleted_at')
                ->whereNotNull('ad.location')
                ->selectRaw('
                    quarter.id as quarter_id,
                    quarter.name as quarter_name,
                    city.id as city_id,
                    city.name as city_name,
                    COUNT(*) as ad_count,
                    ROUND(AVG(ad.price)) as avg_price,
                    ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY ad.price)) as median_price,
                    ROUND(MIN(ad.price)) as min_price,
                    ROUND(MAX(ad.price)) as max_price,
                    AVG(ST_Y(ad.location::geometry)) as lat,
                    AVG(ST_X(ad.location::geometry)) as lng
                ')
                ->groupBy('quarter.id', 'quarter.name', 'city.id', 'city.name')
                ->having(DB::raw('COUNT(*)'), '>=', 1);

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
            $globalMin = (int) $prices->min();
            $globalMax = (int) $prices->max();

            $features = $rows
                ->filter(fn ($row) => $row->lat !== null && $row->lng !== null)
                ->map(function ($row) use ($globalMin, $globalMax) {
                    $range = $globalMax - $globalMin;
                    $intensity = $range > 0
                        ? ($row->median_price - $globalMin) / $range
                        : 0.5;

                    return [
                        'quarter_id' => $row->quarter_id,
                        'quarter_name' => $row->quarter_name,
                        'city_name' => $row->city_name,
                        'lat' => (float) $row->lat,
                        'lng' => (float) $row->lng,
                        'ad_count' => (int) $row->ad_count,
                        'avg_price' => (int) $row->avg_price,
                        'median_price' => (int) $row->median_price,
                        'min_price' => (int) $row->min_price,
                        'max_price' => (int) $row->max_price,
                        'intensity' => round((float) $intensity, 3),
                    ];
                })
                ->values()
                ->all();

            return [
                'features' => $features,
                'price_range' => ['min' => $globalMin, 'max' => $globalMax],
            ];
        });

        return response()->json($data);
    }
}
