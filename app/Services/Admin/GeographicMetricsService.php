<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdInteraction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes geographic supply/demand metrics per city quarter.
 */
final class GeographicMetricsService
{
    private const int CACHE_TTL = 900;

    /**
     * @return array{quarters: array<int, array{name: string, city: string, supply: int, demand: int, ratio: float, avg_price: float, price_trend: float, lat: float, lng: float}>}
     */
    public function get(): array
    {
        return Cache::remember('admin_geographic', self::CACHE_TTL, function () {
            $quarters = DB::table('quarter')
                ->join('city', 'quarter.city_id', '=', 'city.id')
                ->select(
                    'quarter.id',
                    'quarter.name as quarter_name',
                    'city.name as city_name',
                )
                ->get();

            $since30d = now()->subDays(30);
            $since60d = now()->subDays(60);

            $supplyByQuarter = Ad::whereIn('status', [AdStatus::AVAILABLE, AdStatus::RESERVED])
                ->selectRaw('quarter_id, COUNT(*) as count')
                ->groupBy('quarter_id')
                ->pluck('count', 'quarter_id');

            $demandByQuarter = AdInteraction::whereIn('type', [
                AdInteraction::TYPE_VIEW, AdInteraction::TYPE_SEARCH,
                AdInteraction::TYPE_UNLOCK, AdInteraction::TYPE_CONTACT_CLICK,
            ])
                ->where('ad_interactions.created_at', '>=', $since30d)
                ->join('ad', 'ad_interactions.ad_id', '=', 'ad.id')
                ->selectRaw('ad.quarter_id, COUNT(*) as count')
                ->groupBy('ad.quarter_id')
                ->pluck('count', 'quarter_id');

            $avgPriceByQuarter = Ad::whereIn('status', [AdStatus::AVAILABLE, AdStatus::RESERVED])
                ->selectRaw('quarter_id, AVG(price) as avg_price')
                ->groupBy('quarter_id')
                ->pluck('avg_price', 'quarter_id');

            $prevAvgPriceByQuarter = Ad::whereIn('status', [AdStatus::AVAILABLE, AdStatus::RESERVED])
                ->whereBetween('created_at', [$since60d, $since30d])
                ->selectRaw('quarter_id, AVG(price) as avg_price')
                ->groupBy('quarter_id')
                ->pluck('avg_price', 'quarter_id');

            $result = [];
            foreach ($quarters as $q) {
                $supply = $supplyByQuarter[$q->id] ?? 0;
                $demand = $demandByQuarter[$q->id] ?? 0;
                $avgPrice = (float) ($avgPriceByQuarter[$q->id] ?? 0);
                $prevAvgPrice = (float) ($prevAvgPriceByQuarter[$q->id] ?? 0);
                $priceTrend = $prevAvgPrice > 0 ? round((($avgPrice - $prevAvgPrice) / $prevAvgPrice) * 100, 1) : 0;

                $result[] = [
                    'name' => $q->quarter_name,
                    'city' => $q->city_name,
                    'supply' => $supply,
                    'demand' => $demand,
                    'ratio' => $supply > 0 ? round($demand / $supply, 2) : ($demand > 0 ? 999.0 : 0),
                    'avg_price' => round($avgPrice, 0),
                    'price_trend' => $priceTrend,
                    'lat' => 0.0,
                    'lng' => 0.0,
                ];
            }

            usort($result, fn (array $a, array $b) => $b['ratio'] <=> $a['ratio']);

            return ['quarters' => $result];
        });
    }
}
