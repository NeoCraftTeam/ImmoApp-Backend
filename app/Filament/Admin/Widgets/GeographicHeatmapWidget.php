<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\Widget;

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
