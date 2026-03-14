<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\ChartWidget;

class RevenueProjectionChart extends ChartWidget
{
    protected static ?int $sort = 31;

    protected ?string $heading = 'Évolution des revenus mensuels et projections futures';

    protected ?string $description = 'La ligne verte montre les revenus réels mois par mois. La ligne orange en pointillés montre les projections estimées à 3, 6 et 12 mois.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    #[\Override]
    protected function getData(): array
    {
        $service = app(AdminMetricsService::class);
        $revenue = $service->getRevenueAdvancedMetrics();
        $projection = $service->getRevenueProjection();

        $labels = array_keys($revenue['monthly_mrr']);
        $historicalData = array_values($revenue['monthly_mrr']);

        $projectionLabels = ['+3 mois', '+6 mois', '+12 mois'];
        $projectionData = array_fill(0, count($historicalData), null);
        $historicalExtended = array_merge($historicalData, [null, null, null]);

        $lastValue = end($historicalData);
        $projectionLine = array_merge($projectionData, [
            $projection['projection_3m'],
            $projection['projection_6m'],
            $projection['projection_12m'],
        ]);
        $projectionLine[count($historicalData) - 1] = $lastValue;

        return [
            'datasets' => [
                [
                    'label' => 'Revenus réels (FCFA)',
                    'data' => $historicalExtended,
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Projection estimée (FCFA)',
                    'data' => $projectionLine,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderDash' => [5, 5],
                    'backgroundColor' => 'rgba(245, 158, 11, 0.05)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => array_merge($labels, $projectionLabels),
        ];
    }

    #[\Override]
    protected function getType(): string
    {
        return 'line';
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['display' => false]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
