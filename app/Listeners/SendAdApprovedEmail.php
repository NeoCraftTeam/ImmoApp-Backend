<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AdStatus;
use App\Events\AdStatusTransitioned;
use App\Mail\AdApprovedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the teal owner-layout congratulations email when moderation approves an ad.
 *
 * Centralises approval emails so every code path that sets PENDING → AVAILABLE
 * (Filament single/bulk, future API) behaves the same without duplicate sends.
 */
class SendAdApprovedEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(AdStatusTransitioned $event): void
    {
        if ($event->oldStatus !== AdStatus::PENDING || $event->newStatus !== AdStatus::AVAILABLE) {
            return;
        }

        $user = $event->ad->loadMissing('user')->user;
        if ($user === null) {
            return;
        }

        $email = (string) $user->email;
        if ($email === '' || str_ends_with($email, '@clerk.local')) {
            return;
        }

        try {
            Mail::to($user)->queue(new AdApprovedMail($event->ad));
        } catch (Throwable $e) {
            Log::error('SendAdApprovedEmail failed', [
                'ad_id' => $event->ad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(mixed $event, Throwable $exception): void
    {
        Log::error('SendAdApprovedEmail listener failed', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
