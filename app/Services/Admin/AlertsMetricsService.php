<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checks operational alert thresholds: inactive landlords, low-view ads,
 * fraud signals, churn risk, and revenue decline.
 */
final class AlertsMetricsService
{
    private const int CACHE_TTL = 300;

    /**
     * @return array{
     *     inactive_landlords: int,
     *     low_view_ads: int,
     *     fraud_flagged: int,
     *     churn_imminent: int,
     *     revenue_declining: bool
     * }
     */
    public function check(): array
    {
        return Cache::remember('admin_alerts', self::CACHE_TTL, function () {
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
}
