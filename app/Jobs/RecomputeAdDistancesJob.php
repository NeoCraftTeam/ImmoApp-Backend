<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ad;
use App\Services\Geo\NeighborhoodScorecardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Recompute the persisted `distance_*_m` columns on an ad from
 * NeighborhoodScorecardService's nearest-POI walking distances.
 *
 * Why: those columns used to be user-typed in the owner form, so the
 * proximity chips on AdResource could be a guess, a lie, or empty.
 * The scorecard service already computes accurate nearest-POI distances
 * (Overpass + ORS walking matrix). This job persists the four that
 * have a direct scorecard category match.
 *
 * Mapping:
 *   transport  → distance_transport_m
 *   commerce   → distance_shops_m
 *   sante      → distance_hospital_m
 *   education  → distance_school_m
 *
 * `distance_main_road_m` has no scorecard equivalent (the scorecard
 * groups POIs, not road classifications). It is left untouched —
 * existing user-declared values are preserved, new ads keep it null.
 * A future job can backfill it via a dedicated Overpass query on
 * highway=primary|secondary|trunk if the column proves useful.
 *
 * Triggered by `AdObserver::created` (initial compute when location
 * is set) and `AdObserver::updated` (recompute on location change).
 * Idempotent — re-running on the same ad just overwrites with the
 * latest scorecard read.
 */
final class RecomputeAdDistancesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * Map scorecard category keys to ad column names. Only the four
     * categories with a 1:1 column mapping are persisted; the other
     * two scorecard categories (securite, vie_sociale) inform the
     * global keyscore but don't have a dedicated column.
     */
    private const array CATEGORY_TO_COLUMN = [
        'transport' => 'distance_transport_m',
        'commerce' => 'distance_shops_m',
        'sante' => 'distance_hospital_m',
        'education' => 'distance_school_m',
    ];

    public function __construct(public readonly string $adId) {}

    public function handle(NeighborhoodScorecardService $service): void
    {
        $ad = Ad::find($this->adId);
        if ($ad === null || $ad->location === null) {
            return;
        }

        $location = $ad->location;
        // Magellan Point exposes ->latitude / ->longitude getters
        $lat = (float) $location->getLatitude();
        $lng = (float) $location->getLongitude();

        try {
            $scorecard = $service->compute($lat, $lng);
        } catch (\Throwable $e) {
            Log::warning('RecomputeAdDistancesJob: scorecard compute failed', [
                'ad_id' => $this->adId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $categories = $scorecard['categories'];
        $updates = [];

        foreach (self::CATEGORY_TO_COLUMN as $categoryKey => $column) {
            $distance = $categories[$categoryKey]['nearest_poi']['distance_m'] ?? null;
            if (is_int($distance)) {
                $updates[$column] = $distance;
            }
        }

        if ($updates === []) {
            return;
        }

        // `saveQuietly` avoids re-triggering the observer's `updated`
        // hook (which would otherwise re-dispatch this same job).
        $ad->forceFill($updates)->saveQuietly();

        Log::info('RecomputeAdDistancesJob: distances persisted', [
            'ad_id' => $this->adId,
            'updates' => $updates,
            'scorecard_status' => $scorecard['status'],
        ]);
    }
}
