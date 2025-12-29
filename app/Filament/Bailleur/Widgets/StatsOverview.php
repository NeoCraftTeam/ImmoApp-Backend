<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Widgets;

use App\Models\Ad;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    #[\Override]
    protected function getStats(): array
    {
        $user = Auth::user();

        return [
            Stat::make('Mes Biens', Ad::where('user_id', $user->id)->count())
                ->description('Biens mis en location')
                ->icon('heroicon-o-home')
                ->color('success'),

            Stat::make('Revenus', '0 XAF')
                ->description('Revenus locatifs estimés')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning'),
        ];
    }
}
