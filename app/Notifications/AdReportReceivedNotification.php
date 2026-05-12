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

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->hasValidEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): Mailable
    {
        return new AdReportReceivedMail($this->report)
            ->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->report->loadMissing(['ad']);

        $title = $this->report->ad->title;

        return [
            'type' => 'ad_report_received',
            'report_id' => $this->report->id,
            'ad_id' => $this->report->ad_id,
            'message' => "Votre signalement concernant l'annonce « {$title} » a bien été reçu. Nos équipes l'examinent.",
        ];
    }

    private function hasValidEmail(object $notifiable): bool
    {
        $email = data_get($notifiable, 'email');

        return is_string($email)
            && filled($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
