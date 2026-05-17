<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\AdInteraction;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes advanced revenue metrics (MRR, ARPU, LTV, churn) and revenue projections.
 */
final class RevenueMetricsService
{
    private const int CACHE_TTL_SHORT = 300;

    private const int CACHE_TTL_LONG = 900;

    /**
     * @return array{
     *     mrr: float,
     *     arpu: float,
     *     ltv_by_role: array<string, float>,
     *     churn_rate: float,
     *     revenue_by_source: array<string, float>,
     *     monthly_mrr: array<string, float>
     * }
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
     * Linear-regression revenue projection for 3 / 6 / 12 months ahead.
     *
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
}
