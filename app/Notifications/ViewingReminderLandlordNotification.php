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
 * J-1 reminder sent to the LANDLORD the day before a confirmed/pending viewing.
 */
class ViewingReminderLandlordNotification extends Notification implements ShouldQueue
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

        $channels = ['mail'];

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
        $time = substr((string) $this->reservation->slot_starts_at, 0, 5);

        return (new MailMessage)
            ->subject("Rappel visite demain — {$adTitle}")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("Vous avez une visite **demain** avec **{$clientName}** pour **« {$adTitle} »**.")
            ->line("📅 **{$date}** à **{$time}**")
            ->line('Téléphone : '.($this->reservation->client->phone_number ?? 'non renseigné'))
            ->action('Gérer mes visites', config('app.frontend_url').'/owner/viewings')
            ->line('Préparez votre bien et assurez-vous d\'être disponible à l\'heure prévue.')
            ->salutation('L\'équipe KeyHome');
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        $clientName = $this->reservation->client->firstname.' '.$this->reservation->client->lastname;
        $time = substr((string) $this->reservation->slot_starts_at, 0, 5);

        return (new WebPushMessage)
            ->title('Visite demain 📅')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-72x72.png')
            ->body("{$clientName} visite « {$this->reservation->ad->title} » demain à {$time}")
            ->tag('viewing-reminder-landlord-'.$this->reservation->id)
            ->data(['url' => config('app.frontend_url').'/owner/viewings']);
    }
}
