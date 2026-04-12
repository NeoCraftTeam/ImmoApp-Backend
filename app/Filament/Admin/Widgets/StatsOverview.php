<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\PendingAds\PendingAdResource;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected function getStats(): array
    {
        $data = Cache::remember('admin_stats_overview', 300, fn () => $this->computeRawStats());

        return [
            Stat::make('Utilisateurs', $data['monthUsers'])
                ->description('Nouveaux ce mois')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info')
                ->chart($data['userTrend'])
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Note Moyenne', $data['avgRating'].' / 5')
                ->description('Satisfaction globale')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning')
                ->chart($data['reviewTrend'])
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Avis Reçus', $data['reviewCount'])
                ->description('Total des feedbacks')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->chart($data['reviewTrend'])
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Revenus', number_format((float) $data['revenue'], 0, ',', ' ').' FCFA')
                ->description('Gains totaux')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($data['revenueTrend'])
                ->color('success')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Agences', $data['agencyCount'])
                ->description('Partenaires actifs')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->chart($data['agencyTrend'])
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Annonces Actives', $data['activeAds'])
                ->description('Visibles en ligne')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success')
                ->chart($data['adTrend'])
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Prix Moyen', number_format((float) $data['avgPrice'], 0, ',', ' ').' FCFA')
                ->description('Tendance du marché')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('gray')
                ->chart($data['avgPriceTrend'])
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('En Attente', $data['pendingAds'])
                ->description('Annonces à modérer')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->chart($data['pendingTrend'])
                ->url(PendingAdResource::getUrl())
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700 cursor-pointer']),
        ];
    }

    /**
     * Compute all raw stat values for caching. Returns only primitives.
     *
     * @return array{
     *     monthUsers: int,
     *     avgRating: string,
     *     reviewCount: int,
     *     revenue: int|float,
     *     agencyCount: int,
     *     activeAds: int,
     *     avgPrice: int|float,
     *     pendingAds: int,
     *     userTrend: array<int, int>,
     *     reviewTrend: array<int, int>,
     *     revenueTrend: array<int, int>,
     *     agencyTrend: array<int, int>,
     *     adTrend: array<int, int>,
     *     avgPriceTrend: array<int, int>,
     *     pendingTrend: array<int, int>,
     * }
     */
    private function computeRawStats(): array
    {
        return [
            'monthUsers' => User::whereMonth('created_at', now()->month)->count(),
            'avgRating' => number_format((float) Review::avg('rating'), 1),
            'reviewCount' => Review::count(),
            'revenue' => Payment::where('status', PaymentStatus::SUCCESS)->sum('amount'),
            'agencyCount' => Agency::count(),
            'activeAds' => Ad::where('status', 'available')->count(),
            'avgPrice' => Ad::avg('price'),
            'pendingAds' => Ad::where('status', 'pending')->count(),
            'userTrend' => $this->getMonthlyTrend(User::class),
            'reviewTrend' => $this->getMonthlyTrend(Review::class),
            'revenueTrend' => $this->getMonthlyRevenueTrend(),
            'agencyTrend' => $this->getMonthlyTrend(Agency::class),
            'adTrend' => $this->getMonthlyTrend(Ad::class),
            'avgPriceTrend' => $this->getMonthlyAvgPriceTrend(),
            'pendingTrend' => $this->getMonthlyPendingTrend(),
        ];
    }

    /**
     * Get monthly creation trend for a model over the last 7 months.
     *
     * @param  class-string<Model>  $model
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
                ->where('status', PaymentStatus::SUCCESS)
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
