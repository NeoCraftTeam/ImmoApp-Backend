<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ad;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ad $ad,
        public int $daysUntilExpiry
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
        $message = $this->daysUntilExpiry === 0
            ? 'Votre annonce expire aujourd\'hui !'
            : 'Votre annonce expire dans '.$this->daysUntilExpiry.' jour(s).';

        return (new MailMessage)
            ->subject('Votre annonce expire bientôt - KeyHome')
            ->greeting('Bonjour '.$notifiable->name.' !')
            ->line($message)
            ->line('Annonce: "'.$this->ad->title.'"')
            ->action('Renouveler l\'annonce', config('app.frontend_url').'/ads/'.$this->ad->slug.'/renew')
            ->line('Ne manquez pas l\'opportunité de continuer à attirer des clients !');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ad_expiring',
            'title' => 'Annonce expirante',
            'message' => $this->daysUntilExpiry === 0
                ? 'Votre annonce "'.$this->ad->title.'" expire aujourd\'hui'
                : 'Votre annonce "'.$this->ad->title.'" expire dans '.$this->daysUntilExpiry.' jour(s)',
            'ad_id' => $this->ad->id,
            'ad_title' => $this->ad->title,
            'days_until_expiry' => $this->daysUntilExpiry,
        ];
    }
}
