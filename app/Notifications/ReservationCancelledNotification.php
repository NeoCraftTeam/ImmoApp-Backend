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

class ReservationCancelledNotification extends Notification implements ShouldQueue
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
        $cancelledByLabel = $this->cancelledByLabel();

        return (new MailMessage)
            ->subject("Visite annulée — {$adTitle}")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("La visite pour **« {$adTitle} »** le {$date} de {$time} a été annulée par {$cancelledByLabel}.")
            ->when(
                $this->reservation->cancellation_reason,
                fn ($mail) => $mail->line("**Motif :** {$this->reservation->cancellation_reason}")
            )
            ->action('Voir les annonces disponibles', config('app.frontend_url'))
            ->line('Merci de faire confiance à KeyHome !');
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $date = $this->reservation->slot_date->format('d/m/Y');

        return (new WebPushMessage)
            ->title('Visite annulée')
            ->icon('/pwa/icons/icon-192x192.png')
            ->badge('/pwa/icons/icon-72x72.png')
            ->body("Visite du {$date} pour « {$this->reservation->ad->title} » annulée par {$this->cancelledByLabel()}")
            ->tag('viewing-cancelled-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/my/reservations']);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'viewing_reservation_cancelled',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'cancelled_by' => $this->reservation->cancelled_by?->value,
            'cancellation_reason' => $this->reservation->cancellation_reason,
            'message' => "La visite pour « {$this->reservation->ad->title} » le {$this->reservation->slot_date->toDateString()} a été annulée par {$this->cancelledByLabel()}.",
        ];
    }

    private function cancelledByLabel(): string
    {
        return match ($this->reservation->cancelled_by?->value) {
            'client' => 'le locataire',
            'landlord' => 'le propriétaire',
            'system' => 'le système',
            default => 'un utilisateur',
        };
    }
}
