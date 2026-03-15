<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:check-subscription-expirations')->daily();
Schedule::command('app:check-admin-alerts')->daily();
Schedule::command('app:send-monthly-report')->monthlyOn(1, '08:00');
Schedule::job(\App\Jobs\ExpireStaleReservationsJob::class)->everyThirtyMinutes();

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');
