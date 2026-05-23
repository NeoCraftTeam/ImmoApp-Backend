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
 * Sent to the LANDLORD when they mark a reservation as no-show.
 */
class ReservationNoShowLandlordNotification extends Notification implements ShouldQueue
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
        $clientName = $this->reservation->client->firstname.' '.$this->reservation->client->lastname;
        $date = $this->reservation->slot_date->translatedFormat('l d F Y');

        return (new MailMessage)
            ->subject("Absence signalée — {$adTitle}")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("Vous avez signalé l'absence de **{$clientName}** pour la visite du **{$date}** concernant **« {$adTitle} »**.")
            ->line('Le créneau a été libéré. Vous pouvez désormais accepter de nouvelles demandes sur ce créneau.')
            ->action('Gérer mes visites', config('app.frontend_url').'/owner/viewings')
            ->line('Merci d\'utiliser KeyHome !');
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $clientName = $this->reservation->client->firstname.' '.$this->reservation->client->lastname;
        $date = $this->reservation->slot_date->format('d/m/Y');

        return (new WebPushMessage)
            ->title('Absence signalée')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body("Absence de {$clientName} signalée pour le {$date} — « {$this->reservation->ad->title} »")
            ->tag('viewing-noshow-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/owner/viewings']);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'viewing_reservation_no_show',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'client_name' => $this->reservation->client->firstname.' '.$this->reservation->client->lastname,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'message' => "Absence signalée pour « {$this->reservation->ad->title} » le {$this->reservation->slot_date->toDateString()}.",
        ];
    }
}
