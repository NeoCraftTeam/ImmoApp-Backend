<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\PaymentStatus;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\MetricsPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes acquisition-funnel metrics: visitors, traffic sources, new users,
 * conversion rates, and revenue-per-channel.
 */
final class AcquisitionMetricsService
{
    private const int CACHE_TTL = 300;

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
    public function get(string $period = '30d'): array
    {
        $since = MetricsPeriod::toDate($period);

        return Cache::remember("admin_acquisition_v3_{$period}", self::CACHE_TTL, function () use ($since) {
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

            $newRegistrationsByAcquisition = User::where('created_at', '>=', $since)
                ->selectRaw("COALESCE(acquisition_source, 'unknown') as src")
                ->selectRaw('COUNT(*) as cnt')
                ->groupByRaw("COALESCE(acquisition_source, 'unknown')")
                ->pluck('cnt', 'src')
                ->toArray();

            // Single grouped query — replaces the previous per-source foreach loop that
            // fired one Payment::whereIn() per traffic source (N+1).
            $revenueBySource = DB::table('payments')
                ->join('site_visits', 'payments.user_id', '=', 'site_visits.user_id')
                ->where('payments.status', PaymentStatus::SUCCESS->value)
                ->where('payments.created_at', '>=', $since)
                ->where('site_visits.visited_at', '>=', $since)
                ->whereNotNull('site_visits.user_id')
                ->selectRaw("COALESCE(site_visits.source, 'direct') as source, SUM(payments.amount) as total_revenue")
                ->groupByRaw("COALESCE(site_visits.source, 'direct')")
                ->pluck('total_revenue', 'source');

            $userCountBySource = SiteVisit::where('visited_at', '>=', $since)
                ->whereNotNull('user_id')
                ->selectRaw("COALESCE(source, 'direct') as source, COUNT(DISTINCT user_id) as user_count")
                ->groupBy('source')
                ->pluck('user_count', 'source');

            $costPerChannel = [];
            foreach ($userCountBySource as $source => $userCount) {
                $revenue = (float) ($revenueBySource[$source] ?? 0);
                $costPerChannel[$source] = $userCount > 0 ? round($revenue / $userCount, 0) : 0;
            }

            return [
                'unique_visitors' => $uniqueVisitors,
                'sources' => $sources,
                'new_users' => $newUsers,
                'new_registrations_by_acquisition' => $newRegistrationsByAcquisition,
                'conversion_rate' => $conversionRate,
                'cost_per_channel' => $costPerChannel,
            ];
        });
    }
}
