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

// — Sauvegardes (spatie/laravel-backup → disque `backups`, Cloudflare R2) —
// Ordre imposé : `backup:run` d'abord, `backup:clean` ensuite (la purge doit
// compter le backup du jour), `backup:monitor` en dernier pour alerter si
// l'ensemble a échoué. L'ancienne planification lançait `clean` à 01:00 AVANT
// `run` à 02:00, et `monitor` n'était jamais planifié : une chaîne cassée
// pouvait donc rester silencieuse indéfiniment.
//
// `appendOutputTo` est indispensable : sans lui le scheduler redirige vers
// /dev/null et, avec LOG_CHANNEL=nightwatch, plus rien n'atterrit dans
// storage/logs — c'est ce qui a masqué des mois de backups inexistants.
// Toutes les 6 h et non une fois par jour : un dump quotidien laisse perdre
// jusqu'à 24 h de paiements, de messages et de réservations de visites. À
// ~1,35 Mo par archive, 4 dumps quotidiens coûtent ~160 Mo sur la fenêtre de
// 30 jours, très loin du plafond BACKUP_MAX_STORAGE_MB (5 Go).
// 02:00 reste un créneau pour rester en amont de `backup:clean` (03:00).
Schedule::command('backup:run --only-db')
    ->cron('0 2,8,14,20 * * *')
    ->appendOutputTo(storage_path('logs/backup.log'));
// Hebdomadaire : `backup.backup.source.files.include` ne couvre que
// `storage/app` (uploads du disque local), donc l'archive reste légère.
Schedule::command('backup:run --only-files')
    ->weeklyOn(0, '02:20')
    ->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->appendOutputTo(storage_path('logs/backup.log'));
Schedule::command('backup:monitor')
    ->dailyAt('04:00')
    ->appendOutputTo(storage_path('logs/backup.log'));

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
