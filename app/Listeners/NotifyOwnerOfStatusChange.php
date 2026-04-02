<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AdStatus;
use App\Events\AdStatusTransitioned;
use App\Notifications\AdStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyOwnerOfStatusChange implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Notify the ad owner of a status change.
     *
     * Skips DECLINED (owner receives a separate AdDeclinedMail with the reason)
     * and PENDING (owner resubmitted themselves).
     */
    public function handle(AdStatusTransitioned $event): void
    {
        $shouldSkip = in_array($event->newStatus, [AdStatus::DECLINED, AdStatus::PENDING], true);

        if ($shouldSkip || !$event->ad->user) {
            return;
        }

        try {
            $event->ad->user->notify(new AdStatusChanged($event->ad, $event->oldStatus, $event->newStatus));
        } catch (Throwable $e) {
            Log::error("Failed to send AdStatusChanged notification for ad {$event->ad->id}: ".$e->getMessage());
        }
    }

    /**
     * Handle a listener failure.
     */
    public function failed(mixed $event, Throwable $exception): void
    {
        Log::error('NotifyOwnerOfStatusChange listener failed', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
