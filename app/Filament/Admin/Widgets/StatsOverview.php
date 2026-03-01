<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\PendingAds\PendingAdResource;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected function getStats(): array
    {
        $monthUsers = User::whereMonth('created_at', now()->month)->count();
        $avgRating = number_format((float) Review::avg('rating'), 1);
        $reviewCount = Review::count();
        $revenue = Payment::sum('amount');
        $agencyCount = Agency::count();
        $activeAds = Ad::where('status', 'available')->count();
        $avgPrice = Ad::avg('price');
        $pendingAds = Ad::where('status', 'pending')->count();

        return [
            Stat::make('Utilisateurs', $monthUsers)
                ->description('Nouveaux ce mois')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info')
                ->chart($this->getMonthlyTrend(User::class))
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Note Moyenne', $avgRating.' / 5')
                ->description('Satisfaction globale')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning')
                ->chart($this->getMonthlyTrend(Review::class))
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Avis Reçus', $reviewCount)
                ->description('Total des feedbacks')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->chart($this->getMonthlyTrend(Review::class))
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Revenus', number_format((float) $revenue, 0, ',', ' ').' FCFA')
                ->description('Gains totaux')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($this->getMonthlyRevenueTrend())
                ->color('success')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Agences', $agencyCount)
                ->description('Partenaires actifs')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->chart($this->getMonthlyTrend(Agency::class))
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Annonces Actives', $activeAds)
                ->description('Visibles en ligne')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success')
                ->chart($this->getMonthlyTrend(Ad::class))
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Prix Moyen', number_format((float) $avgPrice, 0, ',', ' ').' FCFA')
                ->description('Tendance du marché')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('gray')
                ->chart($this->getMonthlyAvgPriceTrend())
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('En Attente', $pendingAds)
                ->description('Annonces à modérer')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->chart($this->getMonthlyPendingTrend())
                ->url(PendingAdResource::getUrl())
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700 cursor-pointer']),
        ];
    }

    /**
     * Get monthly creation trend for a model over the last 7 months.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return array<int, int>
     */
    private function getMonthlyTrend(string $model): array
    {
        return collect(range(6, 0, -1))
            ->map(fn (int $i): int => $model::query()
                ->whereBetween('created_at', [
                    now()->subMonths($i)->startOfMonth(),
                    now()->subMonths($i)->endOfMonth(),
                ])->count())
            ->values()
            ->all();
    }

    /**
     * Get monthly revenue trend over the last 7 months.
     *
     * @return array<int, int>
     */
    private function getMonthlyRevenueTrend(): array
    {
        return collect(range(6, 0, -1))
            ->map(fn (int $i): int => (int) Payment::query()
                ->whereBetween('created_at', [
                    now()->subMonths($i)->startOfMonth(),
                    now()->subMonths($i)->endOfMonth(),
                ])->sum('amount'))
            ->values()
            ->all();
    }

    /**
     * Get monthly average price trend over the last 7 months.
     *
     * @return array<int, int>
     */
    private function getMonthlyAvgPriceTrend(): array
    {
        return collect(range(6, 0, -1))
            ->map(fn (int $i): int => (int) Ad::query()
                ->whereBetween('created_at', [
                    now()->subMonths($i)->startOfMonth(),
                    now()->subMonths($i)->endOfMonth(),
                ])->avg('price'))
            ->values()
            ->all();
    }

    /**
     * Get monthly pending ads trend over the last 7 months.
     *
     * @return array<int, int>
     */
    private function getMonthlyPendingTrend(): array
    {
        return collect(range(6, 0, -1))
            ->map(fn (int $i): int => Ad::query()
                ->where('status', 'pending')
                ->whereBetween('created_at', [
                    now()->subMonths($i)->startOfMonth(),
                    now()->subMonths($i)->endOfMonth(),
                ])->count())
            ->values()
            ->all();
    }
}
