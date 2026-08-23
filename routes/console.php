<?php

declare(strict_types=1);

use App\Jobs\CompleteStaleReservationsJob;
use App\Jobs\ExpireStaleReservationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:check-subscription-expirations')->daily();
Schedule::command('app:process-subscription-renewals')->daily();
Schedule::command('app:check-admin-alerts')->daily();
// Hourly (not daily): with a 6 h stale cutoff, a daily run left pending
// payments unresolved for up to ~30 h before reconciliation.
Schedule::command('app:cleanup-stale-payments')->hourly();
Schedule::command('app:send-monthly-report')->monthlyOn(1, '08:00');
Schedule::command('app:send-engagement-emails')->dailyAt('08:00');
Schedule::command('app:check-lease-expirations')->dailyAt('09:00');
// Lifecycle sweep — flip Active leases past their lease_end to Expired
// so the dashboard occupancy KPI stays accurate without owner action.
Schedule::command('app:expire-overdue-leases')->dailyAt('03:45');
Schedule::job(ExpireStaleReservationsJob::class)->everyThirtyMinutes();
Schedule::job(CompleteStaleReservationsJob::class)->hourly();

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('model:prune')->daily()->at('04:00');
// Cap Telescope's storage: even with recording disabled in prod, pruning keeps
// `telescope_entries` from growing unbounded if the watcher is ever toggled on.
Schedule::command('telescope:prune --hours=48')->daily()->at('04:15');

// — Automated workflows (Item 25) —
Schedule::command('app:auto-hide-stale-ads')->dailyAt('03:00');
Schedule::command('app:send-post-viewing-thanks')->dailyAt('10:00');

// — Viewing reminders J-1 (Audit Item 5) —
Schedule::command('app:send-viewing-reminders')->dailyAt('08:00');

// — GDPR data retention (P2-29) —
// Step 1: anonymize personal data 30 days after soft-delete (Art. 17 RGPD — droit à l'oubli)
Schedule::command('gdpr:anonymize-deleted')->dailyAt('03:15');
// Step 2: hard-delete anonymized records after 2-year retention threshold
Schedule::command('app:purge-expired-data')->dailyAt('03:30');

// — Smart notification digests (frequency-aware) —
Schedule::command('app:send-search-alert-digests --frequency=immediate')->twiceDaily(8, 18);
Schedule::command('app:send-search-alert-digests --frequency=daily')->dailyAt('08:00');
Schedule::command('app:send-search-alert-digests --frequency=weekly')->weeklyOn(1, '08:00');

// — Behavioral retention push notifications —
Schedule::command('app:send-retention-pushes')->twiceDaily(9, 18);

// — Expire boosted ads (sweep is_boosted/boost_score columns) —
Schedule::command('app:expire-boosted-ads')->hourly();

// — Trust Score nightly recomputation —
Schedule::command('trustscore:recompute')->dailyAt('02:30');

// — Behavioral relevance-score refresh (Sprint 4.3) —
// Re-syncs ads whose interaction counts changed in the last 25h to Meilisearch.
Schedule::command('ads:update-relevance-scores --hours=25')->dailyAt('03:00');
// Full reindex on Sundays (ensures no stale scores accumulate)
Schedule::command('ads:update-relevance-scores --all')->weeklyOn(0, '03:00');
