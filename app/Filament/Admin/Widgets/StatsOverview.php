<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Ad;
use App\Models\Review;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Annonces', Ad::count())
                ->description('Annonces publiées')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => "\$dispatch('setStatusFilter', { filter: 'processed' })",
                ])
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]), // minigraphique

            Stat::make('Nouveaux Utilisateurs', User::whereMonth('created_at', now()->month)->count())
                ->description('Ce mois-ci')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Note Moyenne', number_format(Review::avg('rating'), 1) . ' / 5')
                ->description('⭐ Satisfaction globale')
                ->color('warning'),

            Stat::make('Revenus', '€ ' . number_format(12500, 2))
                ->description('Ce mois')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),
        ];
    }
}
