<?php

declare(strict_types=1);

namespace App\Channels;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notification\SmsService;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel for SMS.
 *
 * Notifications that support this channel must implement toSms(object $notifiable): string.
 * The channel resolves the notifiable's phone number and respects their sms_enabled preference.
 */
final readonly class SmsChannel
{
    public function __construct(private SmsService $sms) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        if (!config('services.sms.enabled', false)) {
            return;
        }

        $phone = $this->resolvePhone($notifiable);
        if (!$phone) {
            return;
        }

        if (!$this->isSmsEnabled($notifiable)) {
            return;
        }

        $message = $notification->toSms($notifiable);
        if (!$message) {
            return;
        }

        $this->sms->send($phone, $message);
    }

    private function resolvePhone(mixed $notifiable): ?string
    {
        if ($notifiable instanceof User) {
            $phone = $notifiable->phone_number;

            return filled($phone) ? (string) $phone : null;
        }

        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $phone = $notifiable->routeNotificationForSms();

            return filled($phone) ? (string) $phone : null;
        }

        return null;
    }

    private function isSmsEnabled(mixed $notifiable): bool
    {
        if (!($notifiable instanceof User)) {
            return true;
        }

        $pref = NotificationPreference::where('user_id', $notifiable->id)->first();
        if ($pref === null) {
            return true;
        }

        return (bool) $pref->sms_enabled;
    }
}
