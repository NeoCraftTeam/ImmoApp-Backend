<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\AdInteraction;
use Filament\Widgets\ChartWidget;

class InteractionTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tendances interactions (plateforme)';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '250px';

    #[\Override]
    protected function getData(): array
    {
        $since = now()->subDays(30);
        $dates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->format('Y-m-d'));
        }

        $views = AdInteraction::where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $favorites = AdInteraction::where('type', AdInteraction::TYPE_FAVORITE)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $contacts = AdInteraction::whereIn('type', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK])
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
                [
                    'label' => 'Contacts',
                    'data' => $dates->map(fn (string $date) => (int) ($contacts[$date] ?? 0)),
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
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
