<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

use App\Models\Ad;
use App\Models\AdInteraction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    #[\Override]
    protected function getStats(): array
    {
        $user = Auth::user();
        $adIds = Ad::where('user_id', $user->id)->pluck('id');
        $since = now()->subDays(30);

        $adCount = $adIds->count();

        if ($adIds->isEmpty()) {
            return [
                Stat::make('Mes Annonces', $adCount)
                    ->description('Total des annonces créées')
                    ->icon('heroicon-o-home-modern')
                    ->color('primary'),
                Stat::make('Vues', 0)
                    ->description('Aucune annonce pour le moment')
                    ->icon('heroicon-o-eye')
                    ->color('gray'),
            ];
        }

        /** @var object{views: int|null, favorites: int|null, contacts: int|null, impressions: int|null} $stats */
        $stats = AdInteraction::whereIn('ad_id', $adIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as views', [AdInteraction::TYPE_VIEW])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as favorites', [AdInteraction::TYPE_FAVORITE])
            ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN 1 ELSE 0 END) as contacts', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as impressions', [AdInteraction::TYPE_IMPRESSION])
            ->first();

        $views = (int) $stats->views;
        $favorites = (int) $stats->favorites;
        $contacts = (int) $stats->contacts;
        $impressions = (int) $stats->impressions;

        $engagementRate = $impressions > 0
            ? round(($favorites + $contacts) / $impressions * 100, 1)
            : 0;

        return [
            Stat::make('Mes Annonces', $adCount)
                ->description('Total des annonces créées')
                ->icon('heroicon-o-home-modern')
                ->color('primary'),

            Stat::make('Vues', number_format($views))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),

            Stat::make('Favoris', number_format($favorites))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger'),

            Stat::make('Contacts', number_format($contacts))
                ->description('Clics contact + téléphone')
                ->descriptionIcon('heroicon-m-phone')
                ->color('warning'),

            Stat::make('Engagement', $engagementRate.'%')
                ->description($engagementRate > 5 ? 'Bon engagement' : 'Engagement faible')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($engagementRate > 5 ? 'success' : 'gray'),
        ];
    }
}
