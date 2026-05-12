<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AdStatus;
use App\Events\AdCreated;
use App\Events\AdStatusTransitioned;
use App\Jobs\MatchSearchAlertsForAdJob;

class MatchSearchAlertsOnAdAvailable
{
    /**
     * Handle AdCreated or AdStatusTransitioned events.
     *
     * Dispatches the search-alert matcher when an ad becomes AVAILABLE.
     */
    public function handle(AdCreated|AdStatusTransitioned $event): void
    {
        $isAvailable = $event instanceof AdCreated
            ? $event->ad->status === AdStatus::AVAILABLE
            : $event->newStatus === AdStatus::AVAILABLE;

        if ($isAvailable) {
            MatchSearchAlertsForAdJob::dispatch($event->ad);
        }
    }
}
