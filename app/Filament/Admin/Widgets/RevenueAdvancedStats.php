<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Services\AdminMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueAdvancedStats extends StatsOverviewWidget
{
    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Revenus détaillés — Performance financière de KeyHome';

    #[\Override]
    protected function getStats(): array
    {
        $metrics = app(AdminMetricsService::class)->getRevenueAdvancedMetrics();

        $sourceLabels = [
            'unlock' => 'Déblocages de contacts',
            'subscription' => 'Abonnements mensuels',
            'boost' => 'Boosts d\'annonces',
            'credit' => 'Achats de crédits',
        ];

        $topSource = 'Aucune donnée';
        $topSourceAmount = 0;
        foreach ($metrics['revenue_by_source'] as $type => $amount) {
            if ($amount > $topSourceAmount) {
                $topSourceAmount = $amount;
                $topSource = $sourceLabels[$type] ?? $type;
            }
        }

        return [
            Stat::make('Revenu mensuel', number_format($metrics['mrr'], 0, ',', ' ').' FCFA')
                ->description('Total des paiements reçus ce mois-ci (toutes sources confondues)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Revenu moyen par utilisateur', number_format($metrics['arpu'], 0, ',', ' ').' FCFA')
                ->description('Montant moyen dépensé par utilisateur actif ce mois-ci')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Taux de perte d\'utilisateurs', $metrics['churn_rate'].'%')
                ->description('Pourcentage d\'utilisateurs actifs le mois dernier qui ne sont pas revenus ce mois-ci')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($metrics['churn_rate'] <= 10 ? 'success' : 'danger')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),

            Stat::make('Source de revenu principale', $topSource)
                ->description($topSourceAmount > 0 ? number_format($topSourceAmount, 0, ',', ' ').' FCFA générés par cette source' : 'Aucun revenu enregistré pour le moment')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('primary')
                ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']),
        ];
    }
}
