<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Ad;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [

            Stat::make('Utilisateurs', User::whereMonth('created_at', now()->month)->count())
                ->description('Nouveaux ce mois')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Note Moyenne', number_format(Review::avg('rating'), 1).' / 5')
                ->description('Satisfaction globale')
                ->color('warning'),

            Stat::make('Avis Reçus', Review::count())
                ->description('Total des feedbacks')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),

            Stat::make('Revenus', number_format(Payment::sum('amount'), 0, ',', ' ').' FCFA')
                ->description('Gains totaux')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart([3, 5, 10, 8, 15, 12, 17])
                ->color('success'),

            Stat::make('Agences', Agency::count())
                ->description('Partenaires actifs')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('Annonces Actives', Ad::where('status', 'available')->count())
                ->description('Visibles en ligne')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success'),

            Stat::make('Prix Moyen', number_format(Ad::avg('price'), 0, ',', ' ').' FCFA')
                ->description('Tendance du marché')
                ->descriptionIcon('heroicon-m-tag')
                ->color('gray'),

            Stat::make('En Attente', Ad::where('status', 'pending')->count())
                ->description('À modérer')
                ->descriptionIcon('heroicon-m-bell')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
        ];
    }
}
