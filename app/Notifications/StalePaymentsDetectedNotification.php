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
        $subjectNoun = $this->count === 1 ? 'paiement bloqué détecté' : 'paiements bloqués détectés';
        $lineNoun = $this->count === 1 ? 'paiement' : 'paiements';
        $actionWord = $this->count === 1 ? 'a été marqué comme échoué' : 'ont été marqués comme échoués';

        return (new MailMessage)
            ->subject("{$this->count} {$subjectNoun}")
            ->greeting('Alerte Paiements')
            ->line("{$this->count} {$lineNoun} en statut PENDING depuis plus de {$this->hours} h {$actionWord}.")
            ->action('Voir les paiements', url('/admin/payments'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $noun = $this->count === 1
            ? 'paiement bloqué marqué comme échoué'
            : 'paiements bloqués marqués comme échoués';

        return [
            'type' => 'stale_payments',
            'count' => $this->count,
            'hours' => $this->hours,
            'message' => "{$this->count} {$noun} après {$this->hours} h.",
        ];
    }
}
