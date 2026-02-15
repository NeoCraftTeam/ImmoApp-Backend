<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Widgets;

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
                Stat::make('Mes Biens', $adCount)
                    ->description('Biens mis en location')
                    ->icon('heroicon-o-home')
                    ->color('success'),
                Stat::make('Vues', 0)
                    ->description('Aucune annonce pour le moment')
                    ->icon('heroicon-o-eye')
                    ->color('gray'),
            ];
        }

        // Real stats from ad_interactions
        $views = AdInteraction::whereIn('ad_id', $adIds)
            ->where('type', AdInteraction::TYPE_VIEW)
            ->where('created_at', '>=', $since)
            ->count();

        $favorites = AdInteraction::whereIn('ad_id', $adIds)
            ->where('type', AdInteraction::TYPE_FAVORITE)
            ->where('created_at', '>=', $since)
            ->count();

        $contacts = AdInteraction::whereIn('ad_id', $adIds)
            ->whereIn('type', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK])
            ->where('created_at', '>=', $since)
            ->count();

        $impressions = AdInteraction::whereIn('ad_id', $adIds)
            ->where('type', AdInteraction::TYPE_IMPRESSION)
            ->where('created_at', '>=', $since)
            ->count();

        $engagementRate = $impressions > 0
            ? round(($favorites + $contacts) / $impressions * 100, 1)
            : 0;

        return [
            Stat::make('Mes Biens', $adCount)
                ->description('Biens mis en location')
                ->icon('heroicon-o-home')
                ->color('success'),

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
                ->description('(Favoris + contacts) / impressions')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($engagementRate > 5 ? 'success' : 'gray'),
        ];
    }
}
