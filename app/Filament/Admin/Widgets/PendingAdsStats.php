<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\AdStatus;
use App\Models\Ad;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingAdsStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    #[\Override]
    protected function getStats(): array
    {
        $pendingCount = Ad::where('status', AdStatus::PENDING)->count();

        if ($pendingCount === 0) {
            return [];
        }

        return [
            Stat::make('🔔 Annonces à valider', $pendingCount)
                ->description('Cliquez sur "À valider" dans la barre latérale')
                ->descriptionIcon('heroicon-m-arrow-left')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
        ];
    }
}
