<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SearchAlertMatch;
use App\Models\User;
use App\Notifications\SearchAlertDigestNotification;
use App\Services\AiDigestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes all pending search-alert matches for a single user and sends
 * a single digest notification (database + mail + WebPush).
 *
 * This job is dispatched once per user by SendSearchAlertDigestsCommand.
 * Keeping it per-user means each job is fast, memory-bounded, and can
 * be retried independently if delivery fails.
 */
final class SendSearchAlertDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public int $timeout = 60;

    public function __construct(public readonly User $user) {}

    public function handle(AiDigestService $aiDigest): void
    {
        // Load pending matches with their relationships in one query.
        $matches = SearchAlertMatch::query()
            ->pending()
            ->where('user_id', $this->user->id)
            ->with(['searchAlert', 'ad.quarter.city'])
            ->orderBy('matched_at')
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        // Group matches by alert so each alert gets its own AI summary.
        $byAlert = $matches->groupBy('search_alert_id');

        /** @var array<string, array{alert: \App\Models\SearchAlert, ads: \App\Models\Ad[], summary: string}> $digestGroups */
        $digestGroups = [];

        foreach ($byAlert as $alertId => $alertMatches) {
            $alert = $alertMatches->first()->searchAlert;

            if (!$alert instanceof \App\Models\SearchAlert) {
                continue;
            }

            $ads = $alertMatches->map(fn ($m) => $m->ad)->filter()->values()->all();

            if (empty($ads)) {
                continue;
            }

            $summary = $aiDigest->summarize($alert, $ads);

            $digestGroups[(string) $alertId] = [
                'alert'   => $alert,
                'ads'     => $ads,
                'summary' => $summary,
            ];
        }

        if (empty($digestGroups)) {
            return;
        }

        try {
            $this->user->notify(new SearchAlertDigestNotification($digestGroups));

            // Mark all processed matches as sent.
            $matchIds = $matches->pluck('id')->all();
            SearchAlertMatch::whereIn('id', $matchIds)->update(['digest_sent_at' => now()]);

            // Update last_notified_at on each alert.
            $alertIds = array_keys($digestGroups);
            \App\Models\SearchAlert::whereIn('id', $alertIds)->update(['last_notified_at' => now()]);

            Log::info('SearchAlert digest sent', [
                'user_id'      => $this->user->id,
                'alert_count'  => count($digestGroups),
                'match_count'  => $matches->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('SearchAlert digest delivery failed', [
                'user_id'   => $this->user->id,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendSearchAlertDigestJob permanently failed', [
            'user_id'   => $this->user->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
