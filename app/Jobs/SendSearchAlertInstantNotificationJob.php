<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FcmToken;
use App\Models\SearchAlertMatch;
use App\Notifications\SearchAlertMatchNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers immediate in-app / e-mail / Web Push for a newly buffered search-alert match
 * and marks the buffer row so twice-daily digests do not duplicate the same ad.
 */
final class SendSearchAlertInstantNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $matchId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $match = SearchAlertMatch::query()
            ->whereKey($this->matchId)
            ->with(['ad.quarter.city', 'searchAlert', 'user'])
            ->first();

        if ($match === null) {
            return;
        }

        if ($match->digest_sent_at !== null) {
            return;
        }

        $ad = $match->ad;
        $alert = $match->searchAlert;
        $user = $match->user;

        if ($ad === null || $alert === null || $user === null || !$user->is_active) {
            return;
        }

        try {
            $user->notify(new SearchAlertMatchNotification($ad, $alert));
        } catch (Throwable $e) {
            Log::error('SearchAlert instant notification failed', [
                'match_id' => $this->matchId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        $match->forceFill(['digest_sent_at' => now()])->save();
        $alert->forceFill(['last_notified_at' => now()])->save();

        if ($alert->notify_push && FcmToken::where('user_id', $user->id)->exists()) {
            SendSearchAlertFcmJob::dispatch($user->id, $ad->id);
        }
    }
}
