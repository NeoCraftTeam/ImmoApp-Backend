<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\SearchAlertMatchMail;
use App\Models\Ad;
use App\Models\SearchAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SearchAlertMatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ad $ad,
        public SearchAlert $alert
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): SearchAlertMatchMail
    {
        return new SearchAlertMatchMail($this->ad, $this->alert, $notifiable)
            ->to($notifiable->email, $notifiable->firstname);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $adUrl = config('app.frontend_url').'/ads/'.urlencode((string) $this->ad->id).'/'.urlencode($this->ad->slug);

        return (new WebPushMessage)
            ->title('Nouvelle annonce pour vous !')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body($this->ad->title.' — '.number_format($this->ad->price ?? 0, 0, ',', ' ').' FCFA')
            ->tag('alert-match-'.$this->ad->id)
            ->data(['url' => $adUrl]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'search_alert_match',
            'title' => 'Nouvelle annonce correspondante',
            'message' => $this->ad->title.' correspond à votre alerte',
            'ad_id' => $this->ad->id,
            'ad_title' => $this->ad->title,
            'ad_slug' => $this->ad->slug,
            'alert_id' => $this->alert->id,
        ];
    }
}
