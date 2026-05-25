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
 * Sent to the LANDLORD when they confirm a viewing reservation.
 * Includes calendar links so they can add the appointment to their own calendar.
 */
class ReservationConfirmedLandlordNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject("Visite confirmée — {$adTitle}")
            ->view('emails.reservation.confirmed-landlord', [
                'reservation' => $this->reservation,
                'notifiable' => $notifiable,
            ]);
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $clientName = $this->reservation->client->firstname.' '.$this->reservation->client->lastname;
        $date = $this->reservation->slot_date->format('d/m/Y');
        $time = substr((string) $this->reservation->slot_starts_at, 0, 5);

        return (new WebPushMessage)
            ->title('Visite confirmée ✅')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body("Visite de {$clientName} le {$date} à {$time} — « {$this->reservation->ad->title} »")
            ->tag('viewing-confirmed-landlord-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/owner/viewings']);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'viewing_reservation_confirmed_by_landlord',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'client_name' => $this->reservation->client->firstname.' '.$this->reservation->client->lastname,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'slot_ends_at' => $this->reservation->slot_ends_at,
            'message' => "Visite confirmée avec {$this->reservation->client->firstname} {$this->reservation->client->lastname} le {$this->reservation->slot_date->toDateString()} à {$this->reservation->slot_starts_at}.",
        ];
    }
}
