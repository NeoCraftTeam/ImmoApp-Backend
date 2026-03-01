<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\AdStatus;
use App\Filament\Admin\Resources\PendingAds\PendingAdResource;
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
            Stat::make('Annonces à valider', $pendingCount)
                ->description('En attente de validation')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url(PendingAdResource::getUrl())
                ->extraAttributes([
                    'class' => 'cursor-pointer ring-1 ring-danger-300 dark:ring-danger-700',
                ]),
        ];
    }
}
