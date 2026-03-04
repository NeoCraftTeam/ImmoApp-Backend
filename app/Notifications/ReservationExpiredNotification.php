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

class ReservationExpiredNotification extends Notification implements ShouldQueue
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
        $time = $this->reservation->slot_starts_at;

        return (new MailMessage)
            ->subject("Créneau de visite expiré — {$adTitle}")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("Votre réservation provisoire pour **« {$adTitle} »** le {$date} à {$time} a expiré — le propriétaire n'a pas confirmé dans les temps.")
            ->action('Choisir un autre créneau', config('app.frontend_url').'/ads/'.$this->reservation->ad->slug)
            ->line('D\'autres créneaux sont peut-être disponibles — réservez-en un nouveau dès maintenant.')
            ->line('Merci de faire confiance à KeyHome !');
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $date = $this->reservation->slot_date->format('d/m/Y');

        return (new WebPushMessage)
            ->title('Créneau expiré ⏰')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("Votre créneau du {$date} pour « {$this->reservation->ad->title} » a expiré. Choisissez un autre créneau.")
            ->tag('viewing-expired-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/ads/'.$this->reservation->ad->slug]);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'viewing_reservation_expired',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'message' => "Votre réservation provisoire pour « {$this->reservation->ad->title} » le {$this->reservation->slot_date->toDateString()} a expiré. Vous pouvez choisir un autre créneau.",
        ];
    }
}
