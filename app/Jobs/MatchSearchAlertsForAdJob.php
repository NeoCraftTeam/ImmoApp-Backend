<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ad;
use App\Models\SearchAlert;
use App\Models\User;
use App\Notifications\SearchAlertMatchNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchSearchAlertsForAdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Ad $ad) {}

    public function handle(): void
    {
        $ad = $this->ad->loadMissing(['quarter.city', 'ad_type']);

        $alerts = SearchAlert::query()
            ->where('is_active', true)
            ->where('user_id', '!=', $ad->user_id)
            ->with('user')
            ->get();

        $notifiedCount = 0;

        foreach ($alerts as $alert) {
            if (!$alert->matchesAd($ad)) {
                continue;
            }

            $user = $alert->user;
            if (!$user instanceof User || !$user->is_active) {
                continue;
            }

            if ($alert->last_notified_at && $alert->last_notified_at->gt(now()->subHours(1))) {
                continue;
            }

            try {
                $user->notify(new SearchAlertMatchNotification($ad, $alert));
                $alert->update(['last_notified_at' => now()]);
                $notifiedCount++;
            } catch (\Throwable $e) {
                Log::error("SearchAlert notification failed for alert {$alert->id}: {$e->getMessage()}");
            }
        }

        if ($notifiedCount > 0) {
            Log::info("SearchAlert: matched ad {$ad->id} against {$notifiedCount} alert(s).");
        }
    }
}
