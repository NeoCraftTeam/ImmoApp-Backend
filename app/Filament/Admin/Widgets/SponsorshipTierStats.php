<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\SponsorshipTier;
use App\Services\Ad\SponsorshipAnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SponsorshipTierStats extends StatsOverviewWidget
{
    protected static ?int $sort = 45;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Feed sponsorisé — performance des 7 derniers jours par tier';

    #[\Override]
    protected function getStats(): array
    {
        $metrics = app(SponsorshipAnalyticsService::class)->tierMetrics(
            now()->subDays(7),
            now(),
        );

        return [
            $this->tierStat(SponsorshipTier::PREMIUM, $metrics, 'warning', 'heroicon-m-star'),
            $this->tierStat(SponsorshipTier::SUBSCRIPTION, $metrics, 'primary', 'heroicon-m-sparkles'),
            $this->tierStat(SponsorshipTier::MANUAL, $metrics, 'info', 'heroicon-m-rocket-launch'),
            $this->tierStat(SponsorshipTier::ORGANIC, $metrics, 'gray', 'heroicon-m-leaf'),
        ];
    }

    /**
     * @param  array<string, array{impressions: int, views: int, unlocks: int, view_rate: float, unlock_rate: float}>  $metrics
     */
    private function tierStat(SponsorshipTier $tier, array $metrics, string $color, string $icon): Stat
    {
        $row = $metrics[$tier->value] ?? [
            'impressions' => 0,
            'views' => 0,
            'unlocks' => 0,
            'view_rate' => 0.0,
            'unlock_rate' => 0.0,
        ];

        $label = match ($tier) {
            SponsorshipTier::PREMIUM => 'Premium',
            SponsorshipTier::SUBSCRIPTION => 'Sponsorisé',
            SponsorshipTier::MANUAL => 'Boosté',
            SponsorshipTier::ORGANIC => 'Organique',
        };

        $impressionsLabel = number_format($row['impressions'], 0, ',', ' ').' impressions';

        $description = sprintf(
            'CTR vues %s%% · CTR déblocages %s%% (%s vues · %s déblocages)',
            number_format($row['view_rate'], 2, ',', ' '),
            number_format($row['unlock_rate'], 2, ',', ' '),
            number_format($row['views'], 0, ',', ' '),
            number_format($row['unlocks'], 0, ',', ' '),
        );

        return Stat::make($label, $impressionsLabel)
            ->description($description)
            ->descriptionIcon($icon)
            ->color($color)
            ->extraAttributes(['class' => 'ring-1 ring-gray-200 dark:ring-gray-700']);
    }
}
