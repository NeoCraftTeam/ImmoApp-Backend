<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Pages;

use App\Filament\Bailleur\Widgets\AdViewsChart;
use App\Filament\Bailleur\Widgets\StatsOverview;
use App\Filament\Bailleur\Widgets\TopAdsTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Espace Bailleur';

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
