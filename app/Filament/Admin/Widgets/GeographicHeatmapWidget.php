<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeographicHeatmapWidget extends Widget
{
    protected static ?int $sort = 50;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.geographic-heatmap';

    /**
     * @return array{quarters: array<int, array{name: string, city: string, supply: int, demand: int, ratio: float, avg_price: float, price_trend: float, lat: float, lng: float}>}
     */
    public function getGeoData(): array
    {
        return app(AdminMetricsService::class)->getGeographicData();
    }

    /**
     * City-level aggregation with PostGIS centroids for the Mapbox map.
     *
     * @return array{cities: list<array<string, mixed>>, ads: list<array<string, mixed>>}
     */
    public function getMapData(): array
    {
        if (Cache::has('admin_geo_map_data')) {
            /** @var array{cities: list<array<string, mixed>>, ads: list<array<string, mixed>>} */
            return Cache::get('admin_geo_map_data');
        }

        $data = $this->computeMapData();
        Cache::put('admin_geo_map_data', $data, 300);

        return $data;
    }

    /**
     * @return array{cities: list<array<string, mixed>>, ads: list<array<string, mixed>>}
     */
    private function computeMapData(): array
    {
        $cities = DB::select('
            SELECT
                c.name,
                COUNT(a.id) AS ad_count,
                ROUND(AVG(a.price)::numeric, 0) AS avg_price,
                ST_Y(ST_Centroid(ST_Collect(a.location::geometry))) AS lat,
                ST_X(ST_Centroid(ST_Collect(a.location::geometry))) AS lng
            FROM city c
            JOIN quarter q ON q.city_id = c.id AND q.deleted_at IS NULL
            JOIN ad a ON a.quarter_id = q.id AND a.deleted_at IS NULL
            WHERE a.location IS NOT NULL
            GROUP BY c.id, c.name
            HAVING COUNT(a.id) > 0
            ORDER BY COUNT(a.id) DESC
        ');

        $ads = DB::select('
            SELECT
                a.id,
                a.title,
                a.price,
                ST_Y(a.location::geometry) AS lat,
                ST_X(a.location::geometry) AS lng,
                c.name AS city,
                q.name AS quarter
            FROM ad a
            JOIN quarter q ON a.quarter_id = q.id AND q.deleted_at IS NULL
            JOIN city c ON q.city_id = c.id AND c.deleted_at IS NULL
            WHERE a.location IS NOT NULL AND a.deleted_at IS NULL
            ORDER BY a.created_at DESC
            LIMIT 500
        ');

        return [
            'cities' => array_map(fn (object $c): array => [
                'name' => $c->name,
                'ad_count' => (int) $c->ad_count,
                'avg_price' => (float) $c->avg_price,
                'lat' => (float) $c->lat,
                'lng' => (float) $c->lng,
            ], $cities),
            'ads' => array_map(fn (object $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'price' => (float) $a->price,
                'lat' => (float) $a->lat,
                'lng' => (float) $a->lng,
                'city' => $a->city,
                'quarter' => $a->quarter,
            ], $ads),
        ];
    }

    public function getMapboxToken(): string
    {
        return (string) config('services.mapbox.token', '');
    }

    /**
     * @return array<int, array{name: string, city: string, supply: int, demand: int, ratio: float, avg_price: float, price_trend: float}>
     */
    public function getTopUnderserved(): array
    {
        $data = $this->getGeoData();

        return array_slice(
            array_filter($data['quarters'], fn (array $q) => $q['demand'] > 0),
            0,
            10
        );
    }
}
