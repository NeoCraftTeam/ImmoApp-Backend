<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\AdReportReceivedMail;
use App\Models\AdReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class AdReportReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AdReport $report,
    ) {}

    public function via(object $notifiable): array
    {
        if (!$this->hasValidEmail($notifiable)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        return new AdReportReceivedMail($this->report);
    }

    private function hasValidEmail(object $notifiable): bool
    {
        $email = data_get($notifiable, 'email');

        return is_string($email)
            && filled($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
