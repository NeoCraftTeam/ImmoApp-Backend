<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

use App\Models\Ad;
use App\Models\AdInteraction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    #[\Override]
    protected function getStats(): array
    {
        $user = Auth::user();
        $userId = $user->id;

        $data = Cache::remember("agency_stats_overview:{$userId}", 120, function () use ($userId): array {
            $adIds = Ad::where('user_id', $userId)->pluck('id');
            $since = now()->subDays(30);

            if ($adIds->isEmpty()) {
                return ['adCount' => 0, 'empty' => true, 'views' => 0, 'favorites' => 0, 'contacts' => 0, 'impressions' => 0];
            }

            /** @var object{views: int|null, favorites: int|null, contacts: int|null, impressions: int|null} $stats */
            $stats = AdInteraction::whereIn('ad_id', $adIds)
                ->where('created_at', '>=', $since)
                ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as views', [AdInteraction::TYPE_VIEW])
                ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as favorites', [AdInteraction::TYPE_FAVORITE])
                ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN 1 ELSE 0 END) as contacts', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK])
                ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as impressions', [AdInteraction::TYPE_IMPRESSION])
                ->first();

            return [
                'adCount' => $adIds->count(),
                'empty' => false,
                'views' => (int) $stats->views,
                'favorites' => (int) $stats->favorites,
                'contacts' => (int) $stats->contacts,
                'impressions' => (int) $stats->impressions,
            ];
        });

        if ($data['empty']) {
            return [
                Stat::make('Mes Annonces', $data['adCount'])
                    ->description('Total des annonces créées')
                    ->icon('heroicon-o-home-modern')
                    ->color('primary'),
                Stat::make('Vues', 0)
                    ->description('Aucune annonce pour le moment')
                    ->icon('heroicon-o-eye')
                    ->color('gray'),
            ];
        }

        $engagementRate = $data['impressions'] > 0
            ? round(($data['favorites'] + $data['contacts']) / $data['impressions'] * 100, 1)
            : 0;

        return [
            Stat::make('Mes Annonces', $data['adCount'])
                ->description('Total des annonces créées')
                ->icon('heroicon-o-home-modern')
                ->color('primary'),

            Stat::make('Vues', number_format($data['views']))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),

            Stat::make('Favoris', number_format($data['favorites']))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger'),

            Stat::make('Contacts', number_format($data['contacts']))
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
