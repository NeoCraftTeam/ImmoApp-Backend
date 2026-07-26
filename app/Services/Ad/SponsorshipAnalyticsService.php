<?php

declare(strict_types=1);

namespace App\Services\Ad;

use App\Enums\SponsorshipTier;
use App\Models\AdInteraction;
use App\Models\SponsoredImpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SponsorshipAnalyticsService
{
    /**
     * Per-tier KPIs over a date window.
     *
     * Impressions come straight from sponsored_impressions (exact, tier
     * recorded at render time). Views and unlocks are bucketed by the
     * ad's CURRENT subscription_tier — an approximation that drifts when
     * an ad's tier changes inside the window, but accurate enough to
     * validate the feed mix without joining historical state.
     *
     * @return array<string, array{
     *     impressions: int,
     *     views: int,
     *     unlocks: int,
     *     view_rate: float,
     *     unlock_rate: float,
     * }>
     */
    public function tierMetrics(Carbon $from, Carbon $to): array
    {
        $impressions = SponsoredImpression::query()
            ->whereBetween('shown_at', [$from, $to])
            ->selectRaw('tier, COUNT(*) as count')
            ->groupBy('tier')
            ->pluck('count', 'tier');

        $views = $this->countInteractionsByTier(
            AdInteraction::TYPE_VIEW,
            $from,
            $to,
        );

        $unlocks = $this->countInteractionsByTier(
            AdInteraction::TYPE_UNLOCK,
            $from,
            $to,
        );

        $rows = [];

        foreach (SponsorshipTier::cases() as $tier) {
            $imp = (int) ($impressions[$tier->value] ?? 0);
            $vw = (int) ($views[$tier->value] ?? 0);
            $un = (int) ($unlocks[$tier->value] ?? 0);

            $rows[$tier->value] = [
                'impressions' => $imp,
                'views' => $vw,
                'unlocks' => $un,
                'view_rate' => $imp > 0 ? round($vw / $imp * 100, 2) : 0.0,
                'unlock_rate' => $imp > 0 ? round($un / $imp * 100, 2) : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<string, int>
     */
    private function countInteractionsByTier(string $type, Carbon $from, Carbon $to): Collection
    {
        return AdInteraction::query()
            ->join('ad', 'ad.id', '=', 'ad_interactions.ad_id')
            ->whereBetween('ad_interactions.created_at', [$from, $to])
            ->where('ad_interactions.type', $type)
            ->selectRaw("COALESCE(ad.subscription_tier, 'organic') as tier, COUNT(*) as count")
            ->groupBy('tier')
            ->pluck('count', 'tier');
    }
}
