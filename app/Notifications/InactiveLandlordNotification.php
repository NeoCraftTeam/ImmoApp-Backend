<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InactiveLandlordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vos annonces attendent des locataires — KeyHome')
            ->greeting('Bonjour '.($notifiable->firstname ?? '').' !')
            ->line('Nous avons remarqué que vous n\'avez pas mis à jour vos annonces depuis plus de 30 jours.')
            ->line('Les annonces actives et à jour reçoivent en moyenne 3x plus de vues.')
            ->action('Gérer mes annonces', url('/owner'))
            ->line('Mettez à jour vos annonces pour attirer plus de locataires !');
    }
}
