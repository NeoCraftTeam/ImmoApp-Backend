<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Enums\UserRole;
use App\Mail\AdSubmissionConfirmationMail;
use App\Mail\NewAdSubmissionMail;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AdObserver
{
    /**
     * Handle the Ad "created" event.
     */
    public function created(Ad $ad): void
    {
        // Auto-boost if agency has active subscription
        app(\App\Services\AdBoostService::class)->autoBoostIfEligible($ad);

        // Notify admins if status is PENDING
        if ($ad->status === AdStatus::PENDING) {
            // 1. Send confirmation to the author
            if ($ad->user) {
                Mail::to($ad->user)->send(new AdSubmissionConfirmationMail($ad));
            }

            // 2. Notify all admins
            $admins = User::where('role', UserRole::ADMIN)->get();
            foreach ($admins as $admin) {
                Mail::to($admin)->send(new NewAdSubmissionMail($ad));
            }
        }
    }

    /**
     * Handle the Ad "updated" event.
     */
    public function updated(Ad $ad): void
    {
        //
    }

    /**
     * Handle the Ad "deleted" event.
     */
    public function deleted(Ad $ad): void
    {
        //
    }

    /**
     * Handle the Ad "restored" event.
     */
    public function restored(Ad $ad): void
    {
        //
    }

    /**
     * Handle the Ad "force deleted" event.
     */
    public function forceDeleted(Ad $ad): void
    {
        //
    }
}
