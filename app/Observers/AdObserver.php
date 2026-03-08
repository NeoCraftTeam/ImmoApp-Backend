<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AdStatus;
use App\Enums\UserRole;
use App\Mail\AdSubmissionConfirmationMail;
use App\Models\Ad;
use App\Models\User;
use App\Notifications\AdStatusChanged;
use App\Notifications\NewAdPending;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

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
                try {
                    Mail::to($ad->user)->send(new AdSubmissionConfirmationMail($ad));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to send ad confirmation email to {$ad->user->email}: ".$e->getMessage());
                }
            }

            // 2. Notify all admins (mail + Filament DB notification + WebPush)
            try {
                $admins = User::where('role', UserRole::ADMIN)->get();
                Notification::send($admins, new NewAdPending($ad));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send admin notifications for new ad: '.$e->getMessage());
            }
        }
    }

    /**
     * Handle the Ad "updated" event.
     *
     * Notify the ad owner when their ad status changes (e.g. approved / rejected by admin).
     * Also re-notify admins when a declined ad is resubmitted (status → PENDING).
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

        // Notify the owner of any status change —
        // except DECLINED (owner already receives AdDeclinedMail with the reason)
        // and PENDING (owner resubmitted themselves — no need to notify them of their own action).
        $notifyOwner = !in_array($newStatus, [AdStatus::DECLINED, AdStatus::PENDING], true);

        if ($notifyOwner && $ad->user) {
            try {
                $ad->user->notify(new AdStatusChanged($ad, $oldStatus, $newStatus));
            } catch (\Throwable $e) {
                Log::error("Failed to send AdStatusChanged notification for ad {$ad->id}: ".$e->getMessage());
            }
        }

        // Re-notify admins when a declined ad is resubmitted for review
        if ($newStatus === AdStatus::PENDING) {
            try {
                $admins = User::where('role', UserRole::ADMIN)->get();
                Notification::send($admins, new NewAdPending($ad));
            } catch (\Throwable $e) {
                Log::error('Failed to send admin resubmission notifications for ad: '.$e->getMessage());
            }
        }
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
