<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Console\Commands\SendViewingReminders;
use App\Models\TentativeReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Audit Item 5 — J-1 reminder sent to the client before their scheduled property viewing.
 *
 * Dispatched by {@see SendViewingReminders} daily at 08:00.
 */
class ViewingReminderNotification extends Notification implements ShouldQueue
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
        $date = $this->reservation->slot_date->translatedFormat('l d F Y');
        $time = substr((string) $this->reservation->slot_starts_at, 0, 5);

        return (new MailMessage)
            ->subject("Rappel : votre visite est demain — {$adTitle}")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("Vous avez une visite prévue **demain {$date} à {$time}** pour le bien :")
            ->line("**{$adTitle}**")
            ->action('Voir ma réservation', config('app.frontend_url').'/my/reservations')
            ->line("En cas d'empêchement, merci de prévenir le propriétaire dès que possible.");
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $date = $this->reservation->slot_date->format('d/m/Y');
        $time = substr((string) $this->reservation->slot_starts_at, 0, 5);

        return (new WebPushMessage)
            ->title('Rappel de visite — KeyHome 🏠')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body("Votre visite de « {$this->reservation->ad->title} » est demain {$date} à {$time}.")
            ->tag('viewing-reminder-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/my/reservations']);
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        $time = substr((string) $this->reservation->slot_starts_at, 0, 5);

        return [
            'type' => 'viewing_reminder',
            'reservation_id' => $this->reservation->id,
            'ad_id' => $this->reservation->ad_id,
            'ad_title' => $this->reservation->ad->title,
            'slot_date' => $this->reservation->slot_date->toDateString(),
            'slot_starts_at' => $this->reservation->slot_starts_at,
            'message' => "Rappel : votre visite pour « {$this->reservation->ad->title} » est demain à {$time}.",
        ];
    }
}
