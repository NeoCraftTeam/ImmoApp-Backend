<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

use App\Models\Ad;
use App\Models\AdInteraction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AdViewsChart extends ChartWidget
{
    protected ?string $heading = 'Vues sur nos annonces';

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

        $views = AdInteraction::whereIn('ad_id', $adIds)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

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
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Favoris',
                    'data' => $dates->map(fn (string $date) => (int) ($favorites[$date] ?? 0)),
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
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
            'plugins' => ['legend' => ['display' => true]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'grid' => ['display' => false]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
