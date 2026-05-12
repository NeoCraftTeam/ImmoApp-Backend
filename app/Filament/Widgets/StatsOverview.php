<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Ad;
use App\Models\Agency;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    #[\Override]
    protected function getStats(): array
    {
        $data = Cache::remember('global_stats_overview', 300, fn () => [
            'users' => User::count(),
            'ads' => Ad::count(),
            'pending' => Ad::where('status', 'pending')->count(),
            'agencies' => Agency::count(),
        ]);

        return [
            Stat::make('Total Users', $data['users'])
                ->description('Total registered users')
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            Stat::make('Total Ads', $data['ads'])
                ->description('Active advertisements')
                ->descriptionIcon('heroicon-m-home')
                ->color('primary'),

            Stat::make('Pending Ads', $data['pending'])
                ->description('Ads waiting for approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Total Agencies', $data['agencies'])
                ->description('Registered agencies')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),
        ];
    }
}
