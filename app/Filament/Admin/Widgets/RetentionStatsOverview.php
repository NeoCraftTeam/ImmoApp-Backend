<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RetentionStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Fidélisation — Les utilisateurs reviennent-ils sur KeyHome ?';

    #[\Override]
    protected function getStats(): array
    {
        $metrics = app(AdminMetricsService::class)->getRetentionMetrics();

        return [
            Stat::make('Actifs aujourd\'hui', number_format($metrics['dau']))
                ->description('Nombre d\'utilisateurs connectés aujourd\'hui')
                ->descriptionIcon('heroicon-m-user')
                ->color('info')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Actifs cette semaine', number_format($metrics['wau']))
                ->description('Utilisateurs connectés au moins 1 fois ces 7 derniers jours')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Actifs ce mois', number_format($metrics['mau']))
                ->description('Utilisateurs connectés au moins 1 fois ces 30 derniers jours')
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Engagement quotidien', $metrics['stickiness'].'%')
                ->description('Ratio actifs aujourd\'hui / actifs ce mois — Plus c\'est haut, plus les gens reviennent chaque jour')
                ->descriptionIcon('heroicon-m-fire')
                ->color($metrics['stickiness'] >= 20 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Taux de retour à 7 jours', $metrics['return_rate_7d'].'%')
                ->description('Pourcentage d\'utilisateurs qui reviennent dans la semaine suivant leur dernière visite')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($metrics['return_rate_7d'] >= 30 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Bailleurs actifs', $metrics['active_landlords'])
                ->description($metrics['inactive_landlords'].' bailleurs n\'ont pas mis à jour leurs annonces depuis 30 jours')
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($metrics['active_landlords'] > $metrics['inactive_landlords'] ? 'success' : 'danger')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),
        ];
    }
}
