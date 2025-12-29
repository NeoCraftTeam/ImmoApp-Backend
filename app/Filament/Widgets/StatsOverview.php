<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Ad;
use App\Models\Agency;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    #[\Override]
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Total registered users')
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            Stat::make('Total Ads', Ad::count())
                ->description('Active advertisements')
                ->descriptionIcon('heroicon-m-home')
                ->color('primary'),

            Stat::make('Pending Ads', Ad::where('status', 'pending')->count())
                ->description('Ads waiting for approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Total Agencies', Agency::count())
                ->description('Registered agencies')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),

            // Optional: If Payment model exists and has amount
            // Stat::make('Revenue', Payment::sum('amount') . ' XAF')
            //    ->description('Total revenue')
            //    ->descriptionIcon('heroicon-m-currency-dollar')
            //    ->color('success'),
        ];
    }
}
