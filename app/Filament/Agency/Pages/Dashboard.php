<?php

declare(strict_types=1);

namespace App\Filament\Agency\Pages;

use App\Filament\Agency\Widgets\AdViewsChart;
use App\Filament\Agency\Widgets\StatsOverview;
use App\Filament\Agency\Widgets\TopAdsTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Tableau de bord Agence';

    #[\Override]
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            AdViewsChart::class,
            TopAdsTable::class,
        ];
    }

    #[\Override]
    public function getColumns(): int|array
    {
        return 2;
    }
}
