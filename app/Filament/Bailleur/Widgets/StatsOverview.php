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
                    ->color('primary')
                    ->chart([0, 0, 0, 0, 0, 0, 0]),
                Stat::make('Vues', 0)
                    ->description('Aucune annonce pour le moment')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->chart([0, 0, 0, 0, 0, 0, 0]),
            ];
        }

        /** @var object{views: int|null, favorites: int|null, impressions: int|null} $stats */
        $stats = AdInteraction::whereIn('ad_id', $adIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as views', [AdInteraction::TYPE_VIEW])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as favorites', [AdInteraction::TYPE_FAVORITE])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as impressions', [AdInteraction::TYPE_IMPRESSION])
            ->first();

        $views = (int) $stats->views;
        $favorites = (int) $stats->favorites;
        $impressions = (int) $stats->impressions;

        $engagementRate = $impressions > 0
            ? round($favorites / $impressions * 100, 1)
            : 0;

        $viewsTrend = $this->getDailyTrend($adIds, AdInteraction::TYPE_VIEW);
        $favoritesTrend = $this->getDailyTrend($adIds, AdInteraction::TYPE_FAVORITE);
        $adsTrend = $this->getMonthlyAdsTrend($user->id);

        return [
            Stat::make('Mes Biens', $adCount)
                ->description('Biens mis en location')
                ->icon('heroicon-o-home')
                ->color('primary')
                ->chart($adsTrend),

            Stat::make('Vues', number_format($views))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info')
                ->chart($viewsTrend),

            Stat::make('Favoris', number_format($favorites))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger')
                ->chart($favoritesTrend),

            Stat::make('Engagement', $engagementRate.'%')
                ->description($engagementRate > 5 ? 'Bon engagement 📈' : 'Engagement faible 📉')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($engagementRate > 5 ? 'success' : 'gray')
                ->chart($viewsTrend),
        ];
    }

    /**
     * Trend journalier d'interactions sur les 7 dernières semaines (par semaine).
     *
     * @param  \Illuminate\Support\Collection<int, string>  $adIds
     * @param  string|array<int, string>  $type
     * @return array<int, int>
     */
    private function getDailyTrend(\Illuminate\Support\Collection $adIds, string|array $type): array
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
