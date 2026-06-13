<?php

declare(strict_types=1);

namespace App\Services\Ad;

use App\Enums\SponsorshipTier;
use App\Jobs\RecordSponsoredImpressionsJob;
use App\Models\Ad;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Distributes a candidate pool of ads across a Facebook-style feed.
 *
 * The 10-slot template enforces ~60% sponsored / 40% organic-or-manual,
 * with diversity (max 3 ads per advertiser) and a quality gate on the
 * three top positions. When a primary tier bucket is empty for a slot,
 * a per-slot fallback chain keeps the page full.
 */
final class AdFeedRankingService
{
    /**
     * Positional template repeated every 10 slots.
     *
     * @var array<int, SponsorshipTier>
     */
    private const array SLOT_TEMPLATE = [
        0 => SponsorshipTier::PREMIUM,
        1 => SponsorshipTier::PREMIUM,
        2 => SponsorshipTier::PREMIUM,
        3 => SponsorshipTier::SUBSCRIPTION,
        4 => SponsorshipTier::ORGANIC,
        5 => SponsorshipTier::SUBSCRIPTION,
        6 => SponsorshipTier::SUBSCRIPTION,
        7 => SponsorshipTier::MANUAL,
        8 => SponsorshipTier::ORGANIC,
        9 => SponsorshipTier::ORGANIC,
    ];

    /**
     * When the primary tier for a slot is empty, walk this chain in order.
     *
     * @var array<string, array<int, SponsorshipTier>>
     */
    private const array FALLBACK_CHAIN = [
        'premium' => [SponsorshipTier::SUBSCRIPTION, SponsorshipTier::MANUAL, SponsorshipTier::ORGANIC],
        'subscription' => [SponsorshipTier::PREMIUM, SponsorshipTier::MANUAL, SponsorshipTier::ORGANIC],
        'manual' => [SponsorshipTier::ORGANIC, SponsorshipTier::SUBSCRIPTION, SponsorshipTier::PREMIUM],
        'organic' => [SponsorshipTier::MANUAL, SponsorshipTier::SUBSCRIPTION, SponsorshipTier::PREMIUM],
    ];

    private const int ADVERTISER_LIMIT_PER_PAGE = 3;

    private const int TOP_SLOT_COUNT = 3;

    private const float MIN_RATING_TOP_SLOTS = 2.5;

    /**
     * Re-order a candidate pool into a paginated, slot-filled page.
     *
     * The candidates collection should already be ordered by relevance
     * (e.g. `computeRankingScore()`) so the highest-ranked ad inside each
     * tier wins its slot first.
     *
     * @param  Collection<int, Ad>  $candidates
     * @return Collection<int, Ad>
     */
    public function distribute(Collection $candidates, int $perPage): Collection
    {
        $buckets = $this->bucketize($candidates);
        $page = collect();
        $advertiserCounts = [];

        for ($slot = 0; $slot < $perPage; $slot++) {
            $primary = self::SLOT_TEMPLATE[$slot % 10];
            $isTopSlot = $slot < self::TOP_SLOT_COUNT;

            $pick = $this->pickFromTier($primary, $buckets, $advertiserCounts, $isTopSlot);

            if ($pick === null) {
                foreach (self::FALLBACK_CHAIN[$primary->value] as $fallback) {
                    $pick = $this->pickFromTier($fallback, $buckets, $advertiserCounts, $isTopSlot);

                    if ($pick !== null) {
                        break;
                    }
                }
            }

            // Last-resort: relax the quality gate so we still fill the slot.
            if ($pick === null && $isTopSlot) {
                $pick = $this->pickFromTier($primary, $buckets, $advertiserCounts, false)
                    ?? $this->pickFromTier(SponsorshipTier::ORGANIC, $buckets, $advertiserCounts, false);
            }

            if ($pick === null) {
                break;
            }

            // Tag the slot on the model instance so recordImpressions() can
            // log the placement. The attribute is transient — AdResource never
            // serialises it and we never call save() on these instances.
            $pick->setAttribute('_feed_slot', $slot);

            $page->push($pick);
            $key = $this->advertiserKey($pick);

            if ($key !== null) {
                $advertiserCounts[$key] = ($advertiserCounts[$key] ?? 0) + 1;
            }
        }

        return $page;
    }

    /**
     * Hand off per-page impression telemetry to a queued job.
     *
     * Previously this method ran the UPDATE + INSERT inline on every
     * feed render — two write queries on the hottest table per request,
     * contending with publishes. Now it dispatches a single job with
     * the flat (ad_id, tier, slot) tuples; the job worker performs the
     * actual writes off the request thread.
     */
    public function recordImpressions(Collection $ads): void
    {
        if ($ads->isEmpty()) {
            return;
        }

        $rows = $ads
            ->filter(fn (Ad $ad) => filled($ad->id))
            ->map(fn (Ad $ad): array => [
                'ad_id' => (string) $ad->id,
                'tier' => $ad->sponsorshipTier()->value,
                'slot' => (int) ($ad->getAttribute('_feed_slot') ?? 0),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        RecordSponsoredImpressionsJob::dispatch($rows, Auth::id() !== null ? (string) Auth::id() : null)
            ->onQueue('telemetry');
    }

    /**
     * Split candidates into four tier-keyed queues. Each ad belongs to
     * exactly one bucket and is consumed at most once.
     *
     * @param  Collection<int, Ad>  $candidates
     * @return array<string, Collection<int, Ad>>
     */
    private function bucketize(Collection $candidates): array
    {
        $buckets = [
            SponsorshipTier::PREMIUM->value => collect(),
            SponsorshipTier::SUBSCRIPTION->value => collect(),
            SponsorshipTier::MANUAL->value => collect(),
            SponsorshipTier::ORGANIC->value => collect(),
        ];

        foreach ($candidates as $ad) {
            $buckets[$ad->sponsorshipTier()->value]->push($ad);
        }

        return $buckets;
    }

    /**
     * Pop the first eligible ad from a tier's queue.
     *
     * @param  array<string, Collection<int, Ad>>  $buckets
     * @param  array<string, int>  $advertiserCounts
     */
    private function pickFromTier(
        SponsorshipTier $tier,
        array &$buckets,
        array $advertiserCounts,
        bool $enforceQualityGate,
    ): ?Ad {
        $bucket = $buckets[$tier->value];

        foreach ($bucket as $index => $ad) {
            $key = $this->advertiserKey($ad);

            if ($key !== null && ($advertiserCounts[$key] ?? 0) >= self::ADVERTISER_LIMIT_PER_PAGE) {
                continue;
            }

            if ($enforceQualityGate && !$this->passesQualityGate($ad)) {
                continue;
            }

            $bucket->forget($index);
            $buckets[$tier->value] = $bucket->values();

            return $ad;
        }

        return null;
    }

    /**
     * Top-slot gate: hide ads with a confirmed low rating (>=3 reviews,
     * avg < 2.5). Unreviewed ads always pass — newcomers deserve a chance.
     */
    private function passesQualityGate(Ad $ad): bool
    {
        $reviewCount = (int) ($ad->reviews_count ?? 0);

        if ($reviewCount < 3) {
            return true;
        }

        return (float) ($ad->reviews_avg_rating ?? 0) >= self::MIN_RATING_TOP_SLOTS;
    }

    /**
     * Agencies enforce diversity at agency level; solo owners at user level.
     */
    private function advertiserKey(Ad $ad): ?string
    {
        if ($ad->agency_id) {
            return 'agency:'.$ad->agency_id;
        }

        if ($ad->user_id) {
            return 'user:'.$ad->user_id;
        }

        return null;
    }
}
