<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ad $ad,
        public AdStatus $oldStatus,
        public AdStatus $newStatus
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Statut de votre annonce modifié')
            ->greeting('Bonjour '.$notifiable->name.' !')
            ->line('Le statut de votre annonce "'.$this->ad->title.'" a été modifié.')
            ->line('Ancien statut: '.$this->oldStatus->getLabel())
            ->line('Nouveau statut: '.$this->newStatus->getLabel())
            ->action('Voir l\'annonce', config('app.frontend_url').'/ads/'.$this->ad->slug)
            ->line('Merci d\'utiliser KeyHome !');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ad_status_changed',
            'title' => 'Statut modifié',
            'message' => 'Le statut de "'.$this->ad->title.'" est passé à '.$this->newStatus->getLabel(),
            'ad_id' => $this->ad->id,
            'ad_title' => $this->ad->title,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
        ];
    }
}
