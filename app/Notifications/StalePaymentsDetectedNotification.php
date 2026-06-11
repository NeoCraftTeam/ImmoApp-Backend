<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StalePaymentsDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $count,
        public int $hours,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚠ {$this->count} paiements bloqués détectés")
            ->greeting('Alerte Paiements')
            ->line("{$this->count} paiement(s) en statut PENDING depuis plus de {$this->hours}h ont été marqués comme échoués.")
            ->action('Voir les paiements', url('/admin/payments'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'stale_payments',
            'count' => $this->count,
            'hours' => $this->hours,
            'message' => "{$this->count} paiement(s) bloqués marqués comme échoués après {$this->hours}h.",
        ];
    }
}
