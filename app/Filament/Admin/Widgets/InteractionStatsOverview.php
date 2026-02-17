<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\AdInteraction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InteractionStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    #[\Override]
    protected function getStats(): array
    {
        $since = now()->subDays(30);

        $views = AdInteraction::where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', $since)
            ->count();

        $favorites = AdInteraction::where('type', AdInteraction::TYPE_FAVORITE)
            ->where('created_at', '>=', $since)
            ->count();

        $shares = AdInteraction::where('type', AdInteraction::TYPE_SHARE)
            ->where('created_at', '>=', $since)
            ->count();

        $contacts = AdInteraction::whereIn('type', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK])
            ->where('created_at', '>=', $since)
            ->count();

        return [
            Stat::make('Vues', number_format($views))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),

            Stat::make('Favoris', number_format($favorites))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger'),

            Stat::make('Partages', number_format($shares))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-share')
                ->color('primary'),

            Stat::make('Contacts', number_format($contacts))
                ->description('Appels + messages')
                ->descriptionIcon('heroicon-m-phone')
                ->color('warning'),
        ];
    }
}
