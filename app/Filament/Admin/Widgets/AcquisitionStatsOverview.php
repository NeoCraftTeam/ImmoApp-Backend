<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AcquisitionStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Acquisition — Comment les utilisateurs découvrent KeyHome';

    #[\Override]
    protected function getStats(): array
    {
        $metrics = app(AdminMetricsService::class)->getAcquisitionMetrics('30d');

        $sourceLabels = [
            'direct' => 'Accès direct (URL tapée)',
            'organic' => 'Recherche Google',
            'social' => 'Réseaux sociaux',
            'referral' => 'Lien depuis un autre site',
            'paid' => 'Publicité payante',
            'email' => 'Campagne email',
        ];

        $topSourceKey = !empty($metrics['sources'])
            ? array_key_first(array_slice($metrics['sources'], 0, 1, true))
            : null;

        $topSource = $topSourceKey !== null
            ? ($sourceLabels[$topSourceKey] ?? $topSourceKey)
            : 'Aucune donnée';

        $topSourceCount = !empty($metrics['sources']) ? array_values($metrics['sources'])[0] : 0;

        return [
            Stat::make('Visiteurs uniques', number_format($metrics['unique_visitors']))
                ->description('Nombre de personnes différentes ayant visité le site ces 30 derniers jours')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Principale source de trafic', $topSource)
                ->description($topSourceCount > 0 ? $topSourceCount.' visiteurs venus par ce canal' : 'Aucun visiteur enregistré pour le moment')
                ->descriptionIcon('heroicon-m-signal')
                ->color('primary')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Taux de conversion visiteur → inscription', $metrics['conversion_rate'].'%')
                ->description('Pourcentage de visiteurs qui créent un compte après avoir visité le site')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($metrics['conversion_rate'] >= 5 ? 'success' : 'warning')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Nouvelles inscriptions', number_format($metrics['new_users']))
                ->description('Nombre de comptes créés ces 30 derniers jours')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),
        ];
    }
}
