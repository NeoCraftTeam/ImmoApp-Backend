<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Ad;
use Filament\Widgets\ChartWidget;

class AdsByTypeChart extends ChartWidget
{
    protected static ?int $sort = 5;

    // protected static ?string $heading = 'Répartition par Type de Bien';

    protected int|string|array $columnSpan = 1;

    #[\Override]
    protected function getData(): array
    {
        $data = Ad::join('ad_type', 'ad.type_id', '=', 'ad_type.id')
            ->selectRaw('ad_type.name as type_name, count(*) as total')
            ->groupBy('ad_type.name')
            ->pluck('total', 'type_name');

        return [
            'datasets' => [
                [
                    'label' => 'Annonces',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                    ],
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
