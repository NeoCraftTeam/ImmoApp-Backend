<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Bailleur\Resources\Viewings\ViewingReservationResource;
use App\Models\TentativeReservation;
use App\Support\PanelUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ReservationCreatedLandlordNotification extends Notification implements ShouldQueue
{
    private function resolveOwnerReservationsUrl(): string
    {
        try {
            return ViewingReservationResource::getUrl(panel: 'bailleur');
        } catch (\Throwable) {
            return PanelUrl::for('bailleur', 'viewings/viewing-reservations');
        }
    }

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

        return (new MailMessage)
            ->subject("Nouvelle demande de visite — {$adTitle}")
            ->view('emails.reservation.created-landlord', [
                'reservation' => $this->reservation,
                'notifiable' => $notifiable,
            ]);
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $clientName = $this->reservation->client->firstname.' '.$this->reservation->client->lastname;
        $date = $this->reservation->slot_date->format('d/m/Y');

        return (new WebPushMessage)
            ->title('Nouvelle demande de visite 🏠')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("{$clientName} veut visiter « {$this->reservation->ad->title} » le {$date}")
            ->tag('viewing-request-'.$this->reservation->id)
            ->data(['url' => $this->resolveOwnerReservationsUrl()]);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'viewing_reservation_created',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'client_name' => $this->reservation->client->firstname.' '.$this->reservation->client->lastname,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'slot_ends_at' => $this->reservation->slot_ends_at,
            'client_message' => $this->reservation->client_message,
            'message' => "Nouvelle demande de visite pour « {$this->reservation->ad->title} » le {$this->reservation->slot_date->toDateString()} à {$this->reservation->slot_starts_at}.",
        ];
    }
}
