<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Ad;
use Filament\Widgets\ChartWidget;

class AdsByCityChart extends ChartWidget
{
    protected static ?int $sort = 6;

    // protected static ?string $heading = 'Annonces par Ville';

    protected int|string|array $columnSpan = 1;

    #[\Override]
    protected function getData(): array
    {
        $data = Ad::join('quarter', 'ad.quarter_id', '=', 'quarter.id')
            ->join('city', 'quarter.city_id', '=', 'city.id')
            ->selectRaw('city.name as city_name, count(*) as total')
            ->groupBy('city.name')
            ->orderByDesc('total')
            ->limit(10) // Top 10 cities
            ->pluck('total', 'city_name');

        return [
            'datasets' => [
                [
                    'label' => 'Nombre d\'annonces',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgb(75, 192, 192)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
