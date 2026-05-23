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

/**
 * Sent to the CLIENT when a confirmed reservation is auto-completed after the slot end time.
 */
class ReservationCompletedClientNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TentativeReservation $reservation,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        $this->reservation->loadMissing(['ad', 'client']);

        $channels = ['database', 'mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $adTitle = $this->reservation->ad->title;
        $date    = $this->reservation->slot_date->translatedFormat('l d F Y');

        return (new MailMessage)
            ->subject("Visite terminée — {$adTitle}")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("Votre visite pour **« {$adTitle} »** du **{$date}** est maintenant terminée.")
            ->line('Nous espérons que cette visite vous a été utile.')
            ->action('Voir les annonces similaires', config('app.frontend_url').'/annonces')
            ->line('Merci de faire confiance à KeyHome !');
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $date = $this->reservation->slot_date->format('d/m/Y');

        return (new WebPushMessage)
            ->title('Visite terminée 🏠')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body("Votre visite du {$date} pour « {$this->reservation->ad->title} » est terminée.")
            ->tag('viewing-completed-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/my/reservations']);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type'           => 'viewing_reservation_completed',
            'reservation_id' => $this->reservation->id,
            'ad_id'          => $this->reservation->ad_id,
            'ad_title'       => $this->reservation->ad->title,
            'slot_date'      => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'message'        => "Votre visite pour « {$this->reservation->ad->title} » du {$this->reservation->slot_date->toDateString()} est terminée.",
        ];
    }
}
