<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Facades\Cache;

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

    #[\Override]
    protected function getData(): array
    {
        $activeFilter = $this->filter;

        return Cache::remember("admin_user_chart:{$activeFilter}", 300, function () use ($activeFilter): array {
            [$start, $perPeriod] = match ($activeFilter) {
                'today' => [now()->startOfDay(), 'perHour'],
                'week' => [now()->startOfWeek(), 'perDay'],
                'month' => [now()->startOfMonth(), 'perDay'],
                'year' => [now()->startOfYear(), 'perMonth'],
                default => [now()->startOfYear(), 'perMonth'],
            };

            $data = Trend::model(User::class)
                ->between(start: $start, end: now())
                ->$perPeriod()
                ->count();

            return [
                'datasets' => [
                    [
                        'label' => 'Nouveaux utilisateurs',
                        'data' => $data->map(fn (TrendValue $value) => $value->aggregate)->values()->all(),
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'borderColor' => 'rgb(59, 130, 246)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                ],
                'labels' => $data->map(fn (TrendValue $value) => $value->date)->values()->all(),
            ];
        });
    }

    protected function getType(): string
    {
        return 'bar'; // 'line', 'bar', 'pie', 'doughnut'
    }

    // Options supplémentaires du graphique
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
