<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class UserChart extends ChartWidget
{
    protected static ?int $sort = 2;

    public ?string $filter = 'year';

    // Largeur du widget
    protected ?string $heading = 'Inscriptions des utilisateurs'; // ou '1/2' pour moitié

    // Filtre pour changer la période
    protected int|string|array $columnSpan = 1;

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Aujourd\'hui',
            'week' => 'Cette semaine',
            'month' => 'Ce mois',
            'year' => 'Cette année',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        // Définir la période selon le filtre
        [$start, $perPeriod] = match ($activeFilter) {
            'today' => [now()->startOfDay(), 'perHour'],
            'week' => [now()->startOfWeek(), 'perDay'],
            'month' => [now()->startOfMonth(), 'perDay'],
            'year' => [now()->startOfYear(), 'perMonth'],
            default => [now()->startOfYear(), 'perMonth'],
        };

        // Récupérer les données avec Trend
        $data = Trend::model(User::class)
            ->between(
                start: $start,
                end: now(),
            )
            ->$perPeriod()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Nouveaux utilisateurs',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => true,
                    'tension' => 0.3, // Courbe lisse
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // 'line', 'bar', 'pie', 'doughnut'
    }

    // Options supplémentaires du graphique
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
