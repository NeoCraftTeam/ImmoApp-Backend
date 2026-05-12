<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActivationStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Activation — Premiers pas des utilisateurs après inscription';

    #[\Override]
    protected function getStats(): array
    {
        $metrics = app(AdminMetricsService::class)->getActivationMetrics();

        $timeLabel = $metrics['avg_time_to_first_action'] < 1
            ? 'Moins d\'1 heure en moyenne'
            : round($metrics['avg_time_to_first_action']).'h en moyenne après la création du compte';

        return [
            Stat::make('Profils complétés', $metrics['profile_completion_rate'].'%')
                ->description('Pourcentage d\'utilisateurs ayant terminé l\'onboarding (remplir leur profil)')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($metrics['profile_completion_rate'] >= 50 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Délai avant 1ère action', round($metrics['avg_time_to_first_action']).'h')
                ->description($timeLabel)
                ->descriptionIcon('heroicon-m-clock')
                ->color($metrics['avg_time_to_first_action'] <= 24 ? 'success' : 'danger')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Bailleurs ayant publié une annonce', $metrics['first_publication_rate'].'%')
                ->description('Pourcentage de bailleurs qui ont publié au moins une annonce')
                ->descriptionIcon('heroicon-m-document-plus')
                ->color($metrics['first_publication_rate'] >= 30 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Clients ayant fait une recherche', $metrics['first_search_rate'].'%')
                ->description('Pourcentage de clients qui ont effectué au moins une recherche de logement')
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color($metrics['first_search_rate'] >= 40 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),
        ];
    }
}
