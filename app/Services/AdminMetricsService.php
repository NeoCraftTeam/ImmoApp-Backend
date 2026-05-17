<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Admin\AcquisitionMetricsService;
use App\Services\Admin\ActivationMetricsService;
use App\Services\Admin\AlertsMetricsService;
use App\Services\Admin\ConversionFunnelService;
use App\Services\Admin\GeographicMetricsService;
use App\Services\Admin\QualityMetricsService;
use App\Services\Admin\RetentionMetricsService;
use App\Services\Admin\RevenueMetricsService;

/**
 * Thin orchestrator / public API facade for all admin analytics.
 *
 * Each analytical domain is handled by a dedicated service injected via the
 * constructor. Callers that already depend on this class continue to work
 * without any changes — all public method signatures are preserved.
 */
class AdminMetricsService
{
    public function __construct(
        private readonly AcquisitionMetricsService $acquisition,
        private readonly ActivationMetricsService $activation,
        private readonly RetentionMetricsService $retention,
        private readonly RevenueMetricsService $revenue,
        private readonly ConversionFunnelService $conversionFunnel,
        private readonly QualityMetricsService $quality,
        private readonly GeographicMetricsService $geographic,
        private readonly AlertsMetricsService $alerts,
    ) {}

    /**
     * @return array{
     *     unique_visitors: int,
     *     sources: array<string, int>,
     *     new_users: int,
     *     new_registrations_by_acquisition: array<string, int>,
     *     conversion_rate: float,
     *     cost_per_channel: array<string, float>
     * }
     */
    public function getAcquisitionMetrics(string $period = '30d'): array
    {
        return $this->acquisition->get($period);
    }

    /**
     * @return array{profile_completion_rate: float, avg_time_to_first_action: float, first_publication_rate: float, first_search_rate: float}
     */
    public function getActivationMetrics(): array
    {
        return $this->activation->get();
    }

    /**
     * @return array{dau: int, wau: int, mau: int, stickiness: float, return_rate_7d: float, active_landlords: int, inactive_landlords: int}
     */
    public function getRetentionMetrics(): array
    {
        return $this->retention->getRetentionMetrics();
    }

    /**
     * @return array<int, array{week: string, cohort_size: int, retention: array<int, float>}>
     */
    public function getCohortRetention(int $weeks = 12): array
    {
        return $this->retention->getCohortRetention($weeks);
    }

    /**
     * @return array{mrr: float, arpu: float, ltv_by_role: array<string, float>, churn_rate: float, revenue_by_source: array<string, float>, monthly_mrr: array<string, float>}
     */
    public function getRevenueAdvancedMetrics(): array
    {
        return $this->revenue->getRevenueAdvancedMetrics();
    }

    /**
     * @return array{projection_3m: float, projection_6m: float, projection_12m: float}
     */
    public function getRevenueProjection(): array
    {
        return $this->revenue->getRevenueProjection();
    }

    /**
     * @return array{steps: array<int, array{label: string, count: int, rate: float, drop_off: float}>}
     */
    public function getConversionFunnel(string $period = '30d'): array
    {
        return $this->conversionFunnel->get($period);
    }

    /**
     * @return array{nps: float, avg_rating: float, report_rate: float, fraud_rate: float, avg_time_to_rent: float, landlord_response_rate: float}
     */
    public function getQualityMetrics(): array
    {
        return $this->quality->get();
    }

    /**
     * @return array{quarters: array<int, array{name: string, city: string, supply: int, demand: int, ratio: float, avg_price: float, price_trend: float, lat: float, lng: float}>}
     */
    public function getGeographicData(): array
    {
        return $this->geographic->get();
    }

    /**
     * @return array{inactive_landlords: int, low_view_ads: int, fraud_flagged: int, churn_imminent: int, revenue_declining: bool}
     */
    public function checkAlerts(): array
    {
        return $this->alerts->check();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllMetricsForExport(): array
    {
        return [
            'acquisition' => $this->getAcquisitionMetrics('30d'),
            'activation' => $this->getActivationMetrics(),
            'retention' => $this->getRetentionMetrics(),
            'revenue' => $this->getRevenueAdvancedMetrics(),
            'funnel' => $this->getConversionFunnel('30d'),
            'quality' => $this->getQualityMetrics(),
            'alerts' => $this->checkAlerts(),
        ];
    }
}
