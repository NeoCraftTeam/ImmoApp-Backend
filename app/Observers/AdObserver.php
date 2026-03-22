<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Events\AdCreated;
use App\Events\AdStatusTransitioned;
use App\Models\Ad;

class AdObserver
{
    /**
     * Ensure tour_config always has a default_scene set before saving.
     */
    public function saving(Ad $ad): void
    {
        if (!empty($ad->tour_config['scenes']) && empty($ad->tour_config['default_scene'])) {
            $config = $ad->tour_config;
            $config['default_scene'] = $config['scenes'][0]['id'];
            $ad->tour_config = $config;
        }
    }

    /**
     * Handle the Ad "created" event.
     */
    public function created(Ad $ad): void
    {
        AdCreated::dispatch($ad);
    }

    /**
     * Handle the Ad "updated" event.
     *
     * Dispatches AdStatusTransitioned when the status column changes.
     */
    public function updated(Ad $ad): void
    {
        if (!$ad->wasChanged('status')) {
            return;
        }

        $original = $ad->getOriginal('status');
        $oldStatus = $original instanceof AdStatus ? $original : AdStatus::tryFrom($original);
        $newStatus = $ad->status;

        if (!$oldStatus || $oldStatus === $newStatus) {
            return;
        }

        AdStatusTransitioned::dispatch($ad, $oldStatus, $newStatus);
    }
}
