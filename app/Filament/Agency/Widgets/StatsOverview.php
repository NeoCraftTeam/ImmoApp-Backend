<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

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
            Stat::make('Mes Annonces', Ad::where('user_id', $user->id)->count())
                ->description('Total des annonces créées')
                ->icon('heroicon-o-home-modern')
                ->color('primary'),

            Stat::make('Vues Total', '0')
                ->description('Visites sur vos annonces')
                ->icon('heroicon-o-eye')
                ->color('gray'),
        ];
    }
}
