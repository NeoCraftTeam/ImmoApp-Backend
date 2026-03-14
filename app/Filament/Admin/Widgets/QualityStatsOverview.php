<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QualityStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 41;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Qualité de service — Satisfaction et fiabilité de la plateforme';

    #[\Override]
    protected function getStats(): array
    {
        $metrics = app(AdminMetricsService::class)->getQualityMetrics();

        $npsLabel = match (true) {
            $metrics['nps'] >= 50 => 'Excellent — Les utilisateurs recommandent activement KeyHome',
            $metrics['nps'] >= 0 => 'Correct — Plus de satisfaits que d\'insatisfaits',
            default => 'À améliorer — Trop d\'utilisateurs insatisfaits',
        };

        return [
            Stat::make('Score de satisfaction', ($metrics['nps'] >= 0 ? '+' : '').$metrics['nps'])
                ->description($npsLabel.' (note moyenne : '.$metrics['avg_rating'].'/5)')
                ->descriptionIcon('heroicon-m-hand-thumb-up')
                ->color($metrics['nps'] >= 0 ? 'success' : 'danger')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Taux de signalement', $metrics['report_rate'].'%')
                ->description('Pourcentage d\'annonces signalées par les utilisateurs ('.$metrics['fraud_rate'].'% suspectées de fraude)')
                ->descriptionIcon('heroicon-m-flag')
                ->color($metrics['report_rate'] <= 5 ? 'success' : 'danger')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Délai moyen de location', $metrics['avg_time_to_rent'].'j')
                ->description('Nombre de jours moyen entre la publication d\'une annonce et sa première réservation')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($metrics['avg_time_to_rent'] <= 30 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Réactivité des bailleurs', $metrics['landlord_response_rate'].'%')
                ->description('Pourcentage de demandes de visite auxquelles les bailleurs ont répondu (confirmé ou refusé)')
                ->descriptionIcon('heroicon-m-chat-bubble-left-ellipsis')
                ->color($metrics['landlord_response_rate'] >= 70 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),
        ];
    }
}
