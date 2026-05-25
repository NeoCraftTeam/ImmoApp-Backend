<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ad;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MatchSearchAlertsForAdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    public function __construct(public Ad $ad) {}

    public function handle(): void
    {
        $ad = $this->ad->loadMissing(['quarter.city', 'ad_type']);

        $bufferedCount = 0;
        $now = now();

        $cityId = $ad->quarter?->city_id;
        $typeId = $ad->type_id;
        $quarterId = $ad->quarter_id;
        $price = $ad->price !== null ? (float) $ad->price : null;

        SearchAlert::query()
            ->where('is_active', true)
            ->where('user_id', '!=', $ad->user_id)
            // DB-level pre-filter: only load alerts whose city/type/quarter match the ad
            // (NULL means "any", so we keep NULLs too). Reduces PHP-side work dramatically.
            ->when($cityId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('city_id')->orWhere('city_id', $cityId)))
            ->when($typeId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('type_id')->orWhere('type_id', $typeId)))
            ->when($quarterId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('quarter_id')->orWhere('quarter_id', $quarterId)))
            ->when($price !== null, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('price_max')->orWhere('price_max', '>=', $price)))
            ->when($price !== null, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('price_min')->orWhere('price_min', '<=', $price)))
            ->with('user')
            ->chunkById(200, function ($alerts) use ($ad, $now, &$bufferedCount): void {
                foreach ($alerts as $alert) {
                    if (!$alert->matchesAd($ad)) {
                        continue;
                    }

                    $user = $alert->user;
                    if (!$user instanceof User || !$user->is_active) {
                        continue;
                    }

                    try {
                        $matchId = (string) Str::uuid();
                        $inserted = DB::table('search_alert_matches')->insertOrIgnore([
                            'id' => $matchId,
                            'search_alert_id' => $alert->id,
                            'user_id' => $user->id,
                            'ad_id' => $ad->id,
                            'matched_at' => $now,
                            'digest_sent_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        if ($inserted > 0) {
                            $bufferedCount++;
                            SendSearchAlertInstantNotificationJob::dispatch($matchId);
                        }
                    } catch (Throwable $e) {
                        Log::error("SearchAlert buffer failed for alert {$alert->id}: {$e->getMessage()}");
                    }
                }
            });

        if ($bufferedCount > 0) {
            Log::info("SearchAlert: ad {$ad->id} matched {$bufferedCount} new alert row(s); instant notifications queued.");
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('MatchSearchAlertsForAdJob failed', [
            'ad_id' => $this->ad->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
