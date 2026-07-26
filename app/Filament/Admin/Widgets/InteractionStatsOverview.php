<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\AdInteraction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class InteractionStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Interactions — Engagement utilisateur sur 30 jours';

    #[\Override]
    protected function getStats(): array
    {
        $data = Cache::remember('admin_interaction_stats', 300, function (): array {
            $since = now()->subDays(30);

            return [
                'views' => AdInteraction::where('type', AdInteraction::TYPE_VIEW)
                    ->where('created_at', '>=', $since)
                    ->count(),
                'favorites' => AdInteraction::where('type', AdInteraction::TYPE_FAVORITE)
                    ->where('created_at', '>=', $since)
                    ->count(),
                'shares' => AdInteraction::where('type', AdInteraction::TYPE_SHARE)
                    ->where('created_at', '>=', $since)
                    ->count(),
                'contacts' => AdInteraction::whereIn('type', [AdInteraction::TYPE_CONTACT_CLICK, AdInteraction::TYPE_PHONE_CLICK])
                    ->where('created_at', '>=', $since)
                    ->count(),
            ];
        });

        // `ring-1` matches every other *StatsOverview* widget on the
        // dashboard — without it this widget renders without a card
        // border while its eight siblings do.
        $ring = ['class' => 'ring-1 ring-gray-200 dark:ring-gray-700'];

        return [
            Stat::make('Vues', number_format($data['views']))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info')
                ->extraAttributes($ring),

            Stat::make('Favoris', number_format($data['favorites']))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger')
                ->extraAttributes($ring),

            Stat::make('Partages', number_format($data['shares']))
                ->description('30 derniers jours')
                ->descriptionIcon('heroicon-m-share')
                ->color('primary')
                ->extraAttributes($ring),

            Stat::make('Contacts', number_format($data['contacts']))
                ->description('Appels + messages')
                ->descriptionIcon('heroicon-m-phone')
                ->color('warning')
                ->extraAttributes($ring),
        ];
    }
}
