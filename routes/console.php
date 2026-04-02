<?php

declare(strict_types=1);

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
Schedule::command('app:cleanup-stale-payments')->daily();
Schedule::command('app:send-monthly-report')->monthlyOn(1, '08:00');
Schedule::command('app:send-engagement-emails')->dailyAt('08:00');
Schedule::command('app:check-lease-expirations')->dailyAt('09:00');
Schedule::job(ExpireStaleReservationsJob::class)->everyThirtyMinutes();

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('model:prune')->daily()->at('04:00');

// — Automated workflows (Item 25) —
Schedule::command('app:auto-hide-stale-ads')->dailyAt('03:00');
Schedule::command('app:send-post-viewing-thanks')->dailyAt('10:00');

// — GDPR data retention (P2-29) —
Schedule::command('app:purge-expired-data')->dailyAt('03:30');
