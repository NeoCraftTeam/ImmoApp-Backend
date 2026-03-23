<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LeaseContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class LeaseExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly LeaseContract $contract,
        public readonly int $daysUntilExpiry
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $adTitle = $this->contract->ad->title ?? $this->contract->unit_reference;
        $days = $this->daysUntilExpiry;

        $subject = $days <= 0
            ? 'Votre bail expire aujourd\'hui'
            : "Votre bail expire dans {$days} jour(s)";

        return (new MailMessage)
            ->subject("{$subject} - KeyHome")
            ->greeting('Bonjour '.$notifiable->firstname.' !')
            ->line("{$subject} pour le bien : \"{$adTitle}\".")
            ->line('Locataire : '.$this->contract->tenant_name)
            ->line('Date de fin : '.$this->contract->lease_end?->format('d/m/Y'))
            ->action('Gérer les baux', config('app.frontend_url').'/owner/bail')
            ->line('Pensez à contacter votre locataire pour discuter du renouvellement.');
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $adTitle = $this->contract->ad->title ?? $this->contract->unit_reference;
        $days = $this->daysUntilExpiry;

        $body = $days <= 0
            ? "Le bail pour \"{$adTitle}\" expire aujourd'hui."
            : "Le bail pour \"{$adTitle}\" expire dans {$days} jour(s).";

        return (new WebPushMessage)
            ->title('Bail expirant bientôt - KeyHome')
            ->body($body)
            ->action('Voir', config('app.frontend_url').'/owner/bail');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lease_expiring',
            'contract_id' => $this->contract->id,
            'days_until_expiry' => $this->daysUntilExpiry,
            'lease_end' => $this->contract->lease_end?->toDateString(),
            'tenant_name' => $this->contract->tenant_name,
            'ad_title' => $this->contract->ad->title,
        ];
    }
}
