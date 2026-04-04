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

        SearchAlert::query()
            ->where('is_active', true)
            ->where('user_id', '!=', $ad->user_id)
            ->with('user')
            ->chunkById(500, function ($alerts) use ($ad, $now, &$bufferedCount): void {
                foreach ($alerts as $alert) {
                    if (!$alert->matchesAd($ad)) {
                        continue;
                    }

                    $user = $alert->user;
                    if (!$user instanceof User || !$user->is_active) {
                        continue;
                    }

                    try {
                        // Buffer the match for the next digest run.
                        // The unique constraint on (search_alert_id, ad_id) prevents duplicates.
                        DB::table('search_alert_matches')->insertOrIgnore([
                            'id' => (string) Str::uuid(),
                            'search_alert_id' => $alert->id,
                            'user_id' => $user->id,
                            'ad_id' => $ad->id,
                            'matched_at' => $now,
                            'digest_sent_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $bufferedCount++;
                    } catch (Throwable $e) {
                        Log::error("SearchAlert buffer failed for alert {$alert->id}: {$e->getMessage()}");
                    }
                }
            });

        if ($bufferedCount > 0) {
            Log::info("SearchAlert: buffered ad {$ad->id} for {$bufferedCount} alert(s) — digest pending.");
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
