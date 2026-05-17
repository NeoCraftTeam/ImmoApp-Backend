<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Ad;
use App\Models\AdReport;
use App\Models\Review;
use App\Models\TentativeReservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Computes platform-quality metrics: NPS, ratings, fraud rates, and landlord responsiveness.
 */
final class QualityMetricsService
{
    private const int CACHE_TTL = 900;

    /**
     * @return array{
     *     nps: float,
     *     avg_rating: float,
     *     report_rate: float,
     *     fraud_rate: float,
     *     avg_time_to_rent: float,
     *     landlord_response_rate: float
     * }
     */
    public function get(): array
    {
        return Cache::remember('admin_quality', self::CACHE_TTL, function () {
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
}
