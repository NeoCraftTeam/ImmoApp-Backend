<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\AdReport;
use App\Models\LeaseContract;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SiteVisit;
use App\Models\TentativeReservation;
use App\Models\UnlockedAd;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminMetricsService
{
    private const int CACHE_TTL_SHORT = 300;

    private const int CACHE_TTL_LONG = 900;

    /**
     * @return array{unique_visitors: int, sources: array<string, int>, new_users: int, conversion_rate: float, cost_per_channel: array<string, float>}
     */
    public function getAcquisitionMetrics(string $period = '30d'): array
    {
        $since = $this->periodToDate($period);

        return Cache::remember("admin_acquisition_{$period}", self::CACHE_TTL_SHORT, function () use ($since) {
            $uniqueVisitors = SiteVisit::where('visited_at', '>=', $since)
                ->distinct('session_id')
                ->count('session_id');

            $sources = SiteVisit::where('visited_at', '>=', $since)
                ->selectRaw("COALESCE(source, 'direct') as source, COUNT(DISTINCT session_id) as count")
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray();

            $newUsers = User::where('created_at', '>=', $since)->count();
            $conversionRate = $uniqueVisitors > 0 ? round(($newUsers / $uniqueVisitors) * 100, 2) : 0;

            $costPerChannel = [];
            $usersBySource = SiteVisit::where('visited_at', '>=', $since)
                ->whereNotNull('user_id')
                ->selectRaw("COALESCE(source, 'direct') as source, COUNT(DISTINCT user_id) as user_count")
                ->groupBy('source')
                ->pluck('user_count', 'source');

            foreach ($usersBySource as $source => $userCount) {
                $revenue = Payment::where('status', PaymentStatus::SUCCESS)
                    ->where('created_at', '>=', $since)
                    ->whereIn('user_id', SiteVisit::where('source', $source)->select('user_id')->whereNotNull('user_id'))
                    ->sum('amount');
                $costPerChannel[$source] = $userCount > 0 ? round((float) $revenue / $userCount, 0) : 0;
            }

            return [
                'unique_visitors' => $uniqueVisitors,
                'sources' => $sources,
                'new_users' => $newUsers,
                'conversion_rate' => $conversionRate,
                'cost_per_channel' => $costPerChannel,
            ];
        });
    }

    /**
     * @return array{profile_completion_rate: float, avg_time_to_first_action: float, first_publication_rate: float, first_search_rate: float}
     */
    public function getActivationMetrics(): array
    {
        return Cache::remember('admin_activation', self::CACHE_TTL_LONG, function () {
            $totalUsers = User::count();
            $completedProfiles = User::whereNotNull('onboarding_completed_at')->count();
            $profileCompletionRate = $totalUsers > 0 ? round(($completedProfiles / $totalUsers) * 100, 1) : 0;

            $avgResult = DB::selectOne('
                SELECT AVG(EXTRACT(EPOCH FROM (first_action - u.created_at)) / 3600) as avg_hours
                FROM users u
                INNER JOIN LATERAL (
                    SELECT MIN(created_at) as first_action
                    FROM ad_interactions
                    WHERE user_id = u.id
                ) ai ON ai.first_action IS NOT NULL
                WHERE u.created_at >= ?
            ', [now()->subMonths(3)]);
            $avgTimeToFirstAction = $avgResult ? (float) $avgResult->avg_hours : 0;

            $totalOwners = User::where('role', UserRole::AGENT)->count();
            $ownersWithAds = User::where('role', UserRole::AGENT)
                ->whereHas('ads')
                ->count();
            $firstPublicationRate = $totalOwners > 0 ? round(($ownersWithAds / $totalOwners) * 100, 1) : 0;

            $totalCustomers = User::where('role', UserRole::CUSTOMER)->count();
            $customersWithSearch = User::where('role', UserRole::CUSTOMER)
                ->whereHas('adInteractions', fn ($q) => $q->where('type', AdInteraction::TYPE_SEARCH))
                ->count();
            $firstSearchRate = $totalCustomers > 0 ? round(($customersWithSearch / $totalCustomers) * 100, 1) : 0;

            return [
                'profile_completion_rate' => $profileCompletionRate,
                'avg_time_to_first_action' => round($avgTimeToFirstAction, 1),
                'first_publication_rate' => $firstPublicationRate,
                'first_search_rate' => $firstSearchRate,
            ];
        });
    }

    /**
     * @return array{dau: int, wau: int, mau: int, stickiness: float, return_rate_7d: float, active_landlords: int, inactive_landlords: int}
     */
    public function getRetentionMetrics(): array
    {
        return Cache::remember('admin_retention', self::CACHE_TTL_SHORT, function () {
            $dau = AdInteraction::where('created_at', '>=', now()->startOfDay())
                ->distinct('user_id')
                ->count('user_id');

            $wau = AdInteraction::where('created_at', '>=', now()->startOfWeek())
                ->distinct('user_id')
                ->count('user_id');

            $mau = AdInteraction::where('created_at', '>=', now()->startOfMonth())
                ->distinct('user_id')
                ->count('user_id');

            $stickiness = $mau > 0 ? round(($dau / $mau) * 100, 1) : 0;

            $weekAgoUsers = AdInteraction::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
                ->distinct('user_id')
                ->pluck('user_id');

            $returnedUsers = $weekAgoUsers->isNotEmpty()
                ? AdInteraction::where('created_at', '>=', now()->subDays(7))
                    ->whereIn('user_id', $weekAgoUsers)
                    ->distinct('user_id')
                    ->count('user_id')
                : 0;

            $returnRate = $weekAgoUsers->count() > 0 ? round(($returnedUsers / $weekAgoUsers->count()) * 100, 1) : 0;

            $activeLandlords = User::where('role', UserRole::AGENT)
                ->whereHas('ads', fn ($q) => $q->where('updated_at', '>=', now()->subDays(30)))
                ->count();

            $totalLandlords = User::where('role', UserRole::AGENT)->count();
            $inactiveLandlords = $totalLandlords - $activeLandlords;

            return [
                'dau' => $dau,
                'wau' => $wau,
                'mau' => $mau,
                'stickiness' => $stickiness,
                'return_rate_7d' => $returnRate,
                'active_landlords' => $activeLandlords,
                'inactive_landlords' => max(0, $inactiveLandlords),
            ];
        });
    }

    /**
     * @return array<int, array{week: string, cohort_size: int, retention: array<int, float>}>
     */
    public function getCohortRetention(int $weeks = 12): array
    {
        return Cache::remember("admin_cohort_{$weeks}", self::CACHE_TTL_LONG, function () use ($weeks) {
            $cohorts = [];

            for ($i = $weeks - 1; $i >= 0; $i--) {
                $cohortStart = now()->subWeeks($i)->startOfWeek();
                $cohortEnd = $cohortStart->copy()->endOfWeek();

                $cohortUsers = User::whereBetween('created_at', [$cohortStart, $cohortEnd])
                    ->pluck('id');

                if ($cohortUsers->isEmpty()) {
                    $cohorts[] = [
                        'week' => $cohortStart->format('d/m'),
                        'cohort_size' => 0,
                        'retention' => [],
                    ];

                    continue;
                }

                $retention = [];
                $checkWeeks = [1, 2, 4, 8, 12];

                foreach ($checkWeeks as $w) {
                    $checkStart = $cohortStart->copy()->addWeeks($w);
                    $checkEnd = $checkStart->copy()->endOfWeek();

                    if ($checkStart->isFuture()) {
                        break;
                    }

                    $activeInWeek = AdInteraction::whereBetween('created_at', [$checkStart, $checkEnd])
                        ->whereIn('user_id', $cohortUsers)
                        ->distinct('user_id')
                        ->count('user_id');

                    $retention[$w] = round(($activeInWeek / $cohortUsers->count()) * 100, 1);
                }

                $cohorts[] = [
                    'week' => $cohortStart->format('d/m'),
                    'cohort_size' => $cohortUsers->count(),
                    'retention' => $retention,
                ];
            }

            return $cohorts;
        });
    }

    /**
     * @return array{mrr: float, arpu: float, ltv_by_role: array<string, float>, churn_rate: float, revenue_by_source: array<string, float>, monthly_mrr: array<string, float>}
     */
    public function getRevenueAdvancedMetrics(): array
    {
        return Cache::remember('admin_revenue_advanced', self::CACHE_TTL_SHORT, function () {
            $mrr = (float) Payment::where('status', PaymentStatus::SUCCESS)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount');

            $activeUsersThisMonth = AdInteraction::where('created_at', '>=', now()->startOfMonth())
                ->distinct('user_id')
                ->count('user_id');
            $arpu = $activeUsersThisMonth > 0 ? round($mrr / $activeUsersThisMonth, 0) : 0;

            $ltvByRole = [];
            foreach (UserRole::cases() as $role) {
                $result = DB::selectOne('
                    SELECT AVG(user_total) as avg_ltv
                    FROM (
                        SELECT p.user_id, SUM(p.amount) as user_total
                        FROM payments p
                        INNER JOIN users u ON u.id = p.user_id
                        WHERE p.status = ? AND u.role = ? AND u.deleted_at IS NULL
                        GROUP BY p.user_id
                    ) sub
                ', [PaymentStatus::SUCCESS->value, $role->value]);
                $ltvByRole[$role->value] = $result ? round((float) $result->avg_ltv, 0) : 0;
            }

            $lastMonthActive = AdInteraction::whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ])->distinct('user_id')->pluck('user_id');

            $thisMonthActive = AdInteraction::where('created_at', '>=', now()->startOfMonth())
                ->distinct('user_id')
                ->pluck('user_id');

            $churned = $lastMonthActive->diff($thisMonthActive)->count();
            $churnRate = $lastMonthActive->count() > 0 ? round(($churned / $lastMonthActive->count()) * 100, 1) : 0;

            $revenueBySource = Payment::where('status', PaymentStatus::SUCCESS)
                ->where('created_at', '>=', now()->startOfMonth())
                ->selectRaw('type, SUM(amount) as total')
                ->groupBy('type')
                ->pluck('total', 'type')
                ->mapWithKeys(fn ($total, $type) => [$type => (float) $total])
                ->toArray();

            $monthlyMrr = [];
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $label = $monthStart->format('M Y');
                $monthlyMrr[$label] = (float) Payment::where('status', PaymentStatus::SUCCESS)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount');
            }

            return [
                'mrr' => $mrr,
                'arpu' => $arpu,
                'ltv_by_role' => $ltvByRole,
                'churn_rate' => $churnRate,
                'revenue_by_source' => $revenueBySource,
                'monthly_mrr' => $monthlyMrr,
            ];
        });
    }

    /**
     * @return array{projection_3m: float, projection_6m: float, projection_12m: float}
     */
    public function getRevenueProjection(): array
    {
        return Cache::remember('admin_revenue_projection', self::CACHE_TTL_LONG, function () {
            $monthlyData = [];
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $monthlyData[] = (float) Payment::where('status', PaymentStatus::SUCCESS)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount');
            }

            $n = count($monthlyData);
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;

            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $monthlyData[$i];
                $sumXY += $i * $monthlyData[$i];
                $sumX2 += $i * $i;
            }

            $denominator = ($n * $sumX2) - ($sumX * $sumX);
            if ($denominator == 0) {
                $slope = 0;
                $intercept = $sumY / $n;
            } else {
                $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
                $intercept = ($sumY - ($slope * $sumX)) / $n;
            }

            return [
                'projection_3m' => max(0, round($intercept + $slope * ($n + 2), 0)),
                'projection_6m' => max(0, round($intercept + $slope * ($n + 5), 0)),
                'projection_12m' => max(0, round($intercept + $slope * ($n + 11), 0)),
            ];
        });
    }

    /**
     * @return array{steps: array<int, array{label: string, count: int, rate: float, drop_off: float}>}
     */
    public function getConversionFunnel(string $period = '30d'): array
    {
        $since = $this->periodToDate($period);

        return Cache::remember("admin_funnel_{$period}", self::CACHE_TTL_SHORT, function () use ($since) {
            $visitors = SiteVisit::where('visited_at', '>=', $since)->distinct('session_id')->count('session_id');
            $inscriptions = User::where('created_at', '>=', $since)->count();
            $searches = AdInteraction::where('type', AdInteraction::TYPE_SEARCH)->where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
            $unlocks = UnlockedAd::where('unlocked_at', '>=', $since)->distinct('user_id')->count('user_id');
            $visits = TentativeReservation::where('created_at', '>=', $since)->distinct('client_id')->count('client_id');
            $locations = LeaseContract::where('created_at', '>=', $since)->count();

            $steps = [
                ['label' => '1. Visiteurs du site', 'count' => $visitors],
                ['label' => '2. Création de compte', 'count' => $inscriptions],
                ['label' => '3. Recherche de logement', 'count' => $searches],
                ['label' => '4. Déblocage d\'un contact', 'count' => $unlocks],
                ['label' => '5. Demande de visite', 'count' => $visits],
                ['label' => '6. Signature de bail', 'count' => $locations],
            ];

            $result = [];
            foreach ($steps as $i => $step) {
                $prevCount = $i > 0 ? $steps[$i - 1]['count'] : $step['count'];
                $rate = $prevCount > 0 ? round(($step['count'] / $prevCount) * 100, 1) : 0;
                $dropOff = $i > 0 ? round(100 - $rate, 1) : 0;

                $result[] = [
                    'label' => $step['label'],
                    'count' => $step['count'],
                    'rate' => $i === 0 ? 100.0 : $rate,
                    'drop_off' => $dropOff,
                ];
            }

            return ['steps' => $result];
        });
    }

    /**
     * @return array{nps: float, avg_rating: float, report_rate: float, fraud_rate: float, avg_time_to_rent: float, landlord_response_rate: float}
     */
    public function getQualityMetrics(): array
    {
        return Cache::remember('admin_quality', self::CACHE_TTL_LONG, function () {
            $avgRating = (float) Review::avg('rating');
            $promoters = Review::where('rating', '>=', 4)->count();
            $detractors = Review::where('rating', '<=', 2)->count();
            $totalReviews = Review::count();
            $nps = $totalReviews > 0 ? round((($promoters - $detractors) / $totalReviews) * 100, 1) : 0;

            $totalAds = Ad::count();
            $totalReports = AdReport::count();
            $reportRate = $totalAds > 0 ? round(($totalReports / $totalAds) * 100, 2) : 0;

            $scamReports = AdReport::where('reason', 'scam')->count();
            $fraudRate = $totalReports > 0 ? round(($scamReports / $totalReports) * 100, 1) : 0;

            $rentResult = DB::selectOne("
                SELECT AVG(EXTRACT(EPOCH FROM (al.created_at - a.created_at)) / 86400) as avg_days
                FROM ad a
                INNER JOIN activity_log al ON al.subject_id::text = a.id::text
                    AND al.subject_type = 'App\\\\Models\\\\Ad'
                    AND al.description = 'updated'
                    AND al.properties::text LIKE '%reserved%'
                WHERE a.status IN ('reserved', 'rent')
            ");
            $avgTimeToRent = $rentResult ? (float) $rentResult->avg_days : 0;

            $totalReservations = TentativeReservation::count();
            $respondedReservations = TentativeReservation::whereIn('status', ['confirmed', 'cancelled'])->count();
            $landlordResponseRate = $totalReservations > 0
                ? round(($respondedReservations / $totalReservations) * 100, 1)
                : 0;

            return [
                'nps' => $nps,
                'avg_rating' => round($avgRating, 1),
                'report_rate' => $reportRate,
                'fraud_rate' => $fraudRate,
                'avg_time_to_rent' => round((float) $avgTimeToRent, 1),
                'landlord_response_rate' => $landlordResponseRate,
            ];
        });
    }

    /**
     * @return array{quarters: array<int, array{name: string, city: string, supply: int, demand: int, ratio: float, avg_price: float, price_trend: float, lat: float, lng: float}>}
     */
    public function getGeographicData(): array
    {
        return Cache::remember('admin_geographic', self::CACHE_TTL_LONG, function () {
            $quarters = DB::table('quarter')
                ->join('city', 'quarter.city_id', '=', 'city.id')
                ->select(
                    'quarter.id',
                    'quarter.name as quarter_name',
                    'city.name as city_name',
                )
                ->get();

            $since30d = now()->subDays(30);
            $since60d = now()->subDays(60);

            $supplyByQuarter = Ad::whereIn('status', [AdStatus::AVAILABLE, AdStatus::RESERVED])
                ->selectRaw('quarter_id, COUNT(*) as count')
                ->groupBy('quarter_id')
                ->pluck('count', 'quarter_id');

            $demandByQuarter = AdInteraction::whereIn('type', [
                AdInteraction::TYPE_VIEW, AdInteraction::TYPE_SEARCH,
                AdInteraction::TYPE_UNLOCK, AdInteraction::TYPE_CONTACT_CLICK,
            ])
                ->where('ad_interactions.created_at', '>=', $since30d)
                ->join('ad', 'ad_interactions.ad_id', '=', 'ad.id')
                ->selectRaw('ad.quarter_id, COUNT(*) as count')
                ->groupBy('ad.quarter_id')
                ->pluck('count', 'quarter_id');

            $avgPriceByQuarter = Ad::whereIn('status', [AdStatus::AVAILABLE, AdStatus::RESERVED])
                ->selectRaw('quarter_id, AVG(price) as avg_price')
                ->groupBy('quarter_id')
                ->pluck('avg_price', 'quarter_id');

            $prevAvgPriceByQuarter = Ad::whereIn('status', [AdStatus::AVAILABLE, AdStatus::RESERVED])
                ->whereBetween('created_at', [$since60d, $since30d])
                ->selectRaw('quarter_id, AVG(price) as avg_price')
                ->groupBy('quarter_id')
                ->pluck('avg_price', 'quarter_id');

            $result = [];
            foreach ($quarters as $q) {
                $supply = $supplyByQuarter[$q->id] ?? 0;
                $demand = $demandByQuarter[$q->id] ?? 0;
                $avgPrice = (float) ($avgPriceByQuarter[$q->id] ?? 0);
                $prevAvgPrice = (float) ($prevAvgPriceByQuarter[$q->id] ?? 0);
                $priceTrend = $prevAvgPrice > 0 ? round((($avgPrice - $prevAvgPrice) / $prevAvgPrice) * 100, 1) : 0;

                $result[] = [
                    'name' => $q->quarter_name,
                    'city' => $q->city_name,
                    'supply' => $supply,
                    'demand' => $demand,
                    'ratio' => $supply > 0 ? round($demand / $supply, 2) : ($demand > 0 ? 999.0 : 0),
                    'avg_price' => round($avgPrice, 0),
                    'price_trend' => $priceTrend,
                    'lat' => 0.0,
                    'lng' => 0.0,
                ];
            }

            usort($result, fn (array $a, array $b) => $b['ratio'] <=> $a['ratio']);

            return ['quarters' => $result];
        });
    }

    /**
     * @return array{inactive_landlords: int, low_view_ads: int, fraud_flagged: int, churn_imminent: int, revenue_declining: bool}
     */
    public function checkAlerts(): array
    {
        return Cache::remember('admin_alerts', self::CACHE_TTL_SHORT, function () {
            $inactiveLandlords = User::where('role', UserRole::AGENT)
                ->whereHas('ads')
                ->whereDoesntHave('ads', fn ($q) => $q->where('updated_at', '>=', now()->subDays(30)))
                ->count();

            $lowViewAds = Ad::where('status', AdStatus::AVAILABLE)
                ->whereDoesntHave('interactions', fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW)->where('created_at', '>=', now()->subDays(14)))
                ->where('created_at', '<=', now()->subDays(14))
                ->count();

            $fraudFlagged = DB::table('ad_reports')
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('owner_id, COUNT(*) as report_count')
                ->groupBy('owner_id')
                ->havingRaw('COUNT(*) >= 3')
                ->count();

            $churnImminent = (int) DB::table('users')
                ->where('role', UserRole::AGENT->value)
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('ad')
                    ->whereColumn('ad.user_id', 'users.id')
                    ->whereNotNull('ad.deleted_at')
                    ->where('ad.deleted_at', '>=', now()->subDays(7)))
                ->count();

            $lastMonthRevenue = (float) Payment::where('status', PaymentStatus::SUCCESS)
                ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                ->sum('amount');
            $thisMonthRevenue = (float) Payment::where('status', PaymentStatus::SUCCESS)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount');
            $revenueDeclining = $lastMonthRevenue > 0 && $thisMonthRevenue < ($lastMonthRevenue * 0.8);

            return [
                'inactive_landlords' => $inactiveLandlords,
                'low_view_ads' => $lowViewAds,
                'fraud_flagged' => $fraudFlagged,
                'churn_imminent' => $churnImminent,
                'revenue_declining' => $revenueDeclining,
            ];
        });
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

    private function periodToDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'year' => now()->startOfYear(),
            default => now()->subDays(30),
        };
    }
}
