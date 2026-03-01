<?php

declare(strict_types=1);

namespace App\Observers;

use App\Listeners\SendAdminActivityEmails;
use Spatie\Activitylog\Models\Activity;

/**
 * Observes the Spatie Activity model to trigger admin email notifications
 * whenever a new activity log entry is created.
 */
class ActivityObserver
{
    public function __construct(private readonly SendAdminActivityEmails $emailSender) {}

    public function created(Activity $activity): void
    {
        $this->emailSender->handle($activity);
    }
}
