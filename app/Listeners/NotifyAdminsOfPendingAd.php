<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AdStatus;
use App\Enums\UserRole;
use App\Events\AdCreated;
use App\Events\AdStatusTransitioned;
use App\Mail\AdSubmissionConfirmationMail;
use App\Mail\FirstAdCelebrationMail;
use App\Models\User;
use App\Notifications\NewAdPending;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAdminsOfPendingAd implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Handle AdCreated or AdStatusTransitioned events.
     *
     * Sends confirmation to the author and notifies all admins when an ad
     * is created with PENDING status or resubmitted (status → PENDING).
     */
    public function handle(AdCreated|AdStatusTransitioned $event): void
    {
        $ad = $event->ad;

        $isPending = $event instanceof AdCreated
            ? $ad->status === AdStatus::PENDING
            : $event->newStatus === AdStatus::PENDING;

        if (!$isPending) {
            return;
        }

        if ($event instanceof AdCreated && $ad->user) {
            try {
                Mail::to($ad->user)->send(new AdSubmissionConfirmationMail($ad));
            } catch (Throwable $e) {
                Log::error("Failed to send ad confirmation email to {$ad->user->email}: ".$e->getMessage());
            }

            $isFirstAd = $ad->user->ads()->count() === 1;
            if ($isFirstAd) {
                try {
                    Mail::to($ad->user)->send(new FirstAdCelebrationMail($ad));
                } catch (Throwable $e) {
                    Log::error("Failed to send first-ad celebration email to {$ad->user->email}: ".$e->getMessage());
                }
            }
        }

        $admins = User::where('role', UserRole::ADMIN)->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new NewAdPending($ad));
            } catch (Throwable $e) {
                Log::error("Failed to send admin notification to {$admin->email} for ad {$ad->id}: ".$e->getMessage());
            }
        }
    }

    /**
     * Handle a listener failure.
     */
    public function failed(mixed $event, Throwable $exception): void
    {
        Log::error('NotifyAdminsOfPendingAd listener failed', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
