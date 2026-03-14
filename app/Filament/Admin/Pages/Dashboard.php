<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AcquisitionStatsOverview;
use App\Filament\Admin\Widgets\ActivationStatsOverview;
use App\Filament\Admin\Widgets\AdsByCityChart;
use App\Filament\Admin\Widgets\AdsByTypeChart;
use App\Filament\Admin\Widgets\CohortRetentionChart;
use App\Filament\Admin\Widgets\ConversionFunnelWidget;
use App\Filament\Admin\Widgets\ExportActionsWidget;
use App\Filament\Admin\Widgets\GeographicHeatmapWidget;
use App\Filament\Admin\Widgets\InteractionStatsOverview;
use App\Filament\Admin\Widgets\InteractionTrendChart;
use App\Filament\Admin\Widgets\PendingAdsStats;
use App\Filament\Admin\Widgets\QualityStatsOverview;
use App\Filament\Admin\Widgets\RetentionStatsOverview;
use App\Filament\Admin\Widgets\RevenueAdvancedStats;
use App\Filament\Admin\Widgets\RevenueChart;
use App\Filament\Admin\Widgets\RevenueProjectionChart;
use App\Filament\Admin\Widgets\StatsOverview;
use App\Filament\Admin\Widgets\UserChart;
use App\Filament\Admin\Widgets\UserStatusChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Tableau de bord';

    #[\Override]
    public function getWidgets(): array
    {
        return [
            // Existing
            StatsOverview::class,
            PendingAdsStats::class,

            // Acquisition & Activation
            AcquisitionStatsOverview::class,
            ActivationStatsOverview::class,

            // Users & Revenue
            UserChart::class,
            RevenueChart::class,
            UserStatusChart::class,
            AdsByTypeChart::class,

            // Interactions
            InteractionStatsOverview::class,
            InteractionTrendChart::class,
            AdsByCityChart::class,

            // Retention
            RetentionStatsOverview::class,
            CohortRetentionChart::class,

            // Revenue Advanced
            RevenueAdvancedStats::class,
            RevenueProjectionChart::class,

            // Conversion & Quality
            ConversionFunnelWidget::class,
            QualityStatsOverview::class,

            // Geographic
            GeographicHeatmapWidget::class,

            // Export
            ExportActionsWidget::class,
        ];
    }
}
