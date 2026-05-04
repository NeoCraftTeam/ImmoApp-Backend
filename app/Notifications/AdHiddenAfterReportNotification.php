<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to the ad owner when an administrator hides the ad after reviewing
 * a report. Includes the reason explaining why the ad has been hidden.
 */
class AdHiddenAfterReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ad $ad,
        public string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if (method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.frontend_url').'/owner/ads/'.$this->ad->id;

        return (new MailMessage)
            ->subject('Votre annonce a été masquée')
            ->view('emails.ad-hidden-after-report', [
                'ownerName' => $notifiable->firstname ?? 'Bonjour',
                'adTitle' => $this->ad->title,
                'reason' => $this->reason,
                'manageUrl' => $url,
            ]);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $url = config('app.frontend_url').'/owner/ads/'.$this->ad->id;

        return (new WebPushMessage)
            ->title('Annonce masquée — KeyHome')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body('Votre annonce « '.$this->ad->title.' » a été masquée par un administrateur.')
            ->tag('ad-hidden-'.$this->ad->id)
            ->data(['url' => $url]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ad_hidden_after_report',
            'title' => 'Annonce masquée',
            'message' => 'Votre annonce « '.$this->ad->title.' » a été masquée. Motif : '.$this->reason,
            'ad_id' => $this->ad->id,
            'ad_title' => $this->ad->title,
            'reason' => $this->reason,
        ];
    }
}
