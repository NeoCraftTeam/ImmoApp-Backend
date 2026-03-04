<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Widgets;

use App\Models\Ad;
use App\Models\AdInteraction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AdViewsChart extends ChartWidget
{
    protected ?string $heading = 'Vues sur mes annonces';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '250px';

    #[\Override]
    protected function getData(): array
    {
        $user = Auth::user();
        $adIds = Ad::where('user_id', $user->id)->pluck('id');

        $since = now()->subDays(30);
        $dates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->format('Y-m-d'));
        }

        // Get daily view counts
        $views = AdInteraction::whereIn('ad_id', $adIds)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        // Get daily favorite counts
        $favorites = AdInteraction::whereIn('ad_id', $adIds)
            ->where('type', AdInteraction::TYPE_FAVORITE)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Vues',
                    'data' => $dates->map(fn (string $date) => (int) ($views[$date] ?? 0)),
                    'borderColor' => 'rgb(13, 148, 136)',
                    'backgroundColor' => 'rgba(13, 148, 136, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(13, 148, 136)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                ],
                [
                    'label' => 'Favoris',
                    'data' => $dates->map(fn (string $date) => (int) ($favorites[$date] ?? 0)),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(59, 130, 246)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                ],
            ],
            'labels' => $dates->map(fn (string $date) => \Carbon\Carbon::parse($date)->format('d/m')),
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 16,
                        'font' => [
                            'family' => 'Inter',
                            'size' => 12,
                            'weight' => '500',
                        ],
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['display' => false],
                    'ticks' => [
                        'font' => ['family' => 'Inter', 'size' => 11],
                    ],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => [
                        'font' => ['family' => 'Inter', 'size' => 11],
                        'maxRotation' => 0,
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'elements' => [
                'line' => [
                    'borderWidth' => 2.5,
                ],
            ],
        ];
    }
}
