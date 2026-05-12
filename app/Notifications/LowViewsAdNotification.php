<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowViewsAdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ad $ad,
    ) {}

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
            ->subject('Votre annonce a besoin d\'attention — KeyHome')
            ->greeting('Bonjour '.($notifiable->firstname ?? '').' !')
            ->line('Votre annonce "'.($this->ad->title ?? 'Sans titre').'" n\'a reçu aucune vue depuis 14 jours.')
            ->line('Quelques conseils pour augmenter la visibilité :')
            ->line('• Mettez à jour les photos et la description')
            ->line('• Vérifiez que le prix est compétitif')
            ->line('• Ajoutez une visite virtuelle 3D')
            ->action('Modifier mon annonce', url('/owner'))
            ->line('Une annonce optimisée attire plus de locataires !');
    }
}
