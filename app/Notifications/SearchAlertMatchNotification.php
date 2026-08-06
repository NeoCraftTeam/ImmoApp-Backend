<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\SearchAlertMatchMail;
use App\Models\Ad;
use App\Models\EmailPreference;
use App\Models\FcmToken;
use App\Models\SearchAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Synchronous notification (runs inside {@see SendSearchAlertInstantNotificationJob}) so
 * digest buffers can be marked only after delivery is initiated successfully.
 */
class SearchAlertMatchNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ad $ad,
        public SearchAlert $alert
    ) {}

    /**
     * @return array<int, string|class-string>
     */
    public function via(object $notifiable): array
    {
        // 'broadcast' : livraison temps réel sur le canal privé `user.{id}`
        // (event `search_alert.match`) — badge + toast live sur web et
        // mobile, sans attendre le prochain polling du centre de
        // notifications.
        $channels = ['database', 'broadcast'];

        if ($this->alert->notify_email) {
            $preference = EmailPreference::getOrCreateForUser($notifiable);
            if ($preference->isEnabled('search_alerts')) {
                $channels[] = 'mail';
            }
        }

        if ($this->alert->notify_push && FcmToken::where('user_id', $notifiable->id)->doesntExist()) {
            if ($notifiable->pushSubscriptions()->exists()) {
                $channels[] = WebPushChannel::class;
            }
        }

        return $channels;
    }

    /**
     * Nom d'event WebSocket court et stable côté clients (au lieu du FQCN
     * `Illuminate\Notifications\Events\BroadcastNotificationCreated`).
     */
    public function broadcastAs(): string
    {
        return 'search_alert.match';
    }

    /**
     * Valeur du champ `type` dans le payload — alignée sur le `type` des
     * données FCM pour que web et mobile partagent le même routage.
     */
    public function broadcastType(): string
    {
        return 'search_alert_match';
    }

    public function toMail(object $notifiable): SearchAlertMatchMail
    {
        return new SearchAlertMatchMail($this->ad, $this->alert, $notifiable)
            ->to($notifiable->email, $notifiable->firstname);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $adUrl = $base.'/ads/'.rawurlencode((string) $this->ad->slug);

        return (new WebPushMessage)
            ->title('Nouvelle annonce pour vous !')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body($this->ad->title.' — '.number_format((float) ($this->ad->price ?? 0), 0, ',', ' ').' FCFA')
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
