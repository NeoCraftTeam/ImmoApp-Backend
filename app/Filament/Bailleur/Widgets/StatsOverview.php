<?php

declare(strict_types=1);

namespace App\Filament\Bailleur\Widgets;

use App\Models\Ad;
use App\Models\AdInteraction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    #[\Override]
    protected function getStats(): array
    {
        $user = Auth::user();
        $userId = $user->id;

        $data = Cache::remember("bailleur_stats_overview:{$userId}", 120, function () use ($userId): array {
            $adIds = Ad::where('user_id', $userId)->pluck('id');
            $since = now()->subDays(30);

            if ($adIds->isEmpty()) {
                return ['adCount' => 0, 'empty' => true, 'views' => 0, 'favorites' => 0, 'impressions' => 0, 'viewsTrend' => [0, 0, 0, 0, 0, 0, 0], 'favoritesTrend' => [0, 0, 0, 0, 0, 0, 0], 'adsTrend' => [0, 0, 0, 0, 0, 0, 0]];
            }

            /** @var object{views: int|null, favorites: int|null, impressions: int|null} $stats */
            $stats = AdInteraction::whereIn('ad_id', $adIds)
                ->where('created_at', '>=', $since)
                ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as views', [AdInteraction::TYPE_VIEW])
                ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as favorites', [AdInteraction::TYPE_FAVORITE])
                ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as impressions', [AdInteraction::TYPE_IMPRESSION])
                ->first();

            return [
                'adCount' => $adIds->count(),
                'empty' => false,
                'views' => (int) $stats->views,
                'favorites' => (int) $stats->favorites,
                'impressions' => (int) $stats->impressions,
                'viewsTrend' => $this->getDailyTrend($adIds, AdInteraction::TYPE_VIEW),
                'favoritesTrend' => $this->getDailyTrend($adIds, AdInteraction::TYPE_FAVORITE),
                'adsTrend' => $this->getMonthlyAdsTrend($userId),
            ];
        });

        if ($data['empty']) {
            return [
                Stat::make('Mes Biens', $data['adCount'])
                    ->description('Biens mis en location')
                    ->icon('heroicon-o-home')
                    ->color('primary')
                    ->chart([0, 0, 0, 0, 0, 0, 0]),
                Stat::make('Vues', 0)
                    ->description('Aucune annonce pour le moment')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->chart([0, 0, 0, 0, 0, 0, 0]),
            ];
        }

        $engagementRate = $data['impressions'] > 0
            ? round($data['favorites'] / $data['impressions'] * 100, 1)
            : 0;

        return [
            Stat::make('Mes Biens', $data['adCount'])
                ->description('Biens mis en location')
                ->icon('heroicon-o-home')
                ->color('primary')
                ->chart($data['adsTrend']),

            Stat::make('Vues', number_format($data['views']))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info')
                ->chart($data['viewsTrend']),

            Stat::make('Favoris', number_format($data['favorites']))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger')
                ->chart($data['favoritesTrend']),

            Stat::make('Engagement', $engagementRate.'%')
                ->description($engagementRate > 5 ? 'Bon engagement' : 'Engagement faible')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($engagementRate > 5 ? 'success' : 'gray')
                ->chart($data['viewsTrend']),
        ];
    }

    /**
     * Trend journalier d'interactions sur les 7 dernières semaines (par semaine).
     *
     * @param  Collection<int, string>  $adIds
     * @param  string|array<int, string>  $type
     * @return array<int, int>
     */
    private function getDailyTrend(Collection $adIds, string|array $type): array
    {
        $types = is_array($type) ? $type : [$type];

        return collect(range(6, 0, -1))
            ->map(fn (int $i): int => AdInteraction::whereIn('ad_id', $adIds)
                ->whereIn('type', $types)
                ->whereBetween('created_at', [
                    now()->subWeeks($i)->startOfWeek(),
                    now()->subWeeks($i)->endOfWeek(),
                ])->count())
            ->values()
            ->all();
    }

    /**
     * Trend mensuel du nombre d'annonces créées sur les 7 derniers mois.
     *
     * @return array<int, int>
     */
    private function getMonthlyAdsTrend(string $userId): array
    {
        return collect(range(6, 0, -1))
            ->map(fn (int $i): int => Ad::where('user_id', $userId)
                ->whereBetween('created_at', [
                    now()->subMonths($i)->startOfMonth(),
                    now()->subMonths($i)->endOfMonth(),
                ])->count())
            ->values()
            ->all();
    }
}
