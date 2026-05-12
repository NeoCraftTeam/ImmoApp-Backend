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
use App\Filament\Admin\Widgets\RegistrationsByAcquisitionChart;
use App\Filament\Admin\Widgets\RetentionStatsOverview;
use App\Filament\Admin\Widgets\RevenueAdvancedStats;
use App\Filament\Admin\Widgets\RevenueChart;
use App\Filament\Admin\Widgets\RevenueProjectionChart;
use App\Filament\Admin\Widgets\StatsOverview;
use App\Filament\Admin\Widgets\UserChart;
use App\Filament\Admin\Widgets\UserStatusChart;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Tableau de bord';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('tab')
                            ->label('Section')
                            ->options([
                                'overview' => 'Vue d\'ensemble',
                                'acquisition' => 'Acquisition & Activation',
                                'revenue' => 'Utilisateurs & Revenus',
                                'engagement' => 'Engagement & Interactions',
                                'retention' => 'Rétention',
                                'advanced' => 'Avancé & Géographie',
                            ])
                            ->default('overview')
                            ->selectablePlaceholder(false),
                    ])
                    ->columns(1),
            ]);
    }

    #[\Override]
    public function getWidgets(): array
    {
        $tab = $this->filters['tab'] ?? 'overview';

        return match ($tab) {
            'acquisition' => [
                AcquisitionStatsOverview::class,
                RegistrationsByAcquisitionChart::class,
                ActivationStatsOverview::class,
            ],
            'revenue' => [
                UserChart::class,
                RevenueChart::class,
                UserStatusChart::class,
                AdsByTypeChart::class,
            ],
            'engagement' => [
                InteractionStatsOverview::class,
                InteractionTrendChart::class,
                AdsByCityChart::class,
            ],
            'retention' => [
                RetentionStatsOverview::class,
                CohortRetentionChart::class,
            ],
            'advanced' => [
                RevenueAdvancedStats::class,
                RevenueProjectionChart::class,
                ConversionFunnelWidget::class,
                QualityStatsOverview::class,
                GeographicHeatmapWidget::class,
                ExportActionsWidget::class,
            ],
            default => [
                StatsOverview::class,
                PendingAdsStats::class,
                GeographicHeatmapWidget::class,
            ],
        };
    }
}
