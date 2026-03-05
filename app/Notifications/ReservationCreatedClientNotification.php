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

class ReservationCreatedClientNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject("Visite en attente de confirmation — {$adTitle}")
            ->view('emails.reservation.created-client', [
                'reservation' => $this->reservation,
                'notifiable' => $notifiable,
            ]);
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $date = $this->reservation->slot_date->format('d/m/Y');

        return (new WebPushMessage)
            ->title('Demande de visite envoyée ✓')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("Visite pour « {$this->reservation->ad->title} » le {$date} — en attente de confirmation")
            ->tag('viewing-client-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/my/reservations']);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'viewing_reservation_confirmed_by_client',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'slot_ends_at' => $this->reservation->slot_ends_at,
            'expires_at' => $this->reservation->expires_at->toIso8601String(),
            'message' => "Votre créneau de visite pour « {$this->reservation->ad->title} » le {$this->reservation->slot_date->toDateString()} est retenu. Le propriétaire vous contactera sous 24h.",
        ];
    }
}
