<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TentativeReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ReservationConfirmedClientNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TentativeReservation $reservation,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $adTitle = $this->reservation->ad->title;
        $date = $this->reservation->slot_date->translatedFormat('l d F Y');
        $time = $this->reservation->slot_starts_at.' – '.$this->reservation->slot_ends_at;
        $landlordName = $this->reservation->ad->user->firstname.' '.$this->reservation->ad->user->lastname;

        return (new MailMessage)
            ->subject("Visite confirmée ! — {$adTitle}")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("**Bonne nouvelle !** {$landlordName} a confirmé votre visite.")
            ->line("🏠 **Bien :** {$adTitle}")
            ->line("📅 **Date :** {$date}")
            ->line("⏰ **Horaire :** {$time}")
            ->line('Pensez à vous présenter à l\'heure et à apporter vos pièces justificatives.')
            ->action('Voir mes visites', config('app.frontend_url').'/my/reservations')
            ->line('Merci de faire confiance à KeyHome !');
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $date = $this->reservation->slot_date->format('d/m/Y');

        return (new WebPushMessage)
            ->title('Visite confirmée ! 🎉')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("Votre visite du {$date} pour « {$this->reservation->ad->title} » est confirmée !")
            ->tag('viewing-confirmed-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/my/reservations']);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'viewing_reservation_confirmed',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'slot_ends_at' => $this->reservation->slot_ends_at,
            'message' => "Votre visite pour « {$this->reservation->ad->title} » le {$this->reservation->slot_date->toDateString()} à {$this->reservation->slot_starts_at} est confirmée !",
        ];
    }
}
