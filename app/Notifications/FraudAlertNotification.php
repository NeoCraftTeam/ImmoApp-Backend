<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FraudAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $flaggedUser,
        public int $reportCount,
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
            ->subject('⚠ Alerte fraude — Pic de signalements')
            ->greeting('Alerte Admin')
            ->line("L'utilisateur {$this->flaggedUser->firstname} {$this->flaggedUser->lastname} (ID: {$this->flaggedUser->id}) a reçu {$this->reportCount} signalements en 7 jours.")
            ->line('Action recommandée : vérifier les annonces de cet utilisateur et prendre les mesures nécessaires.')
            ->action('Voir les signalements', url('/admin/ad-reports'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'fraud_alert',
            'user_id' => $this->flaggedUser->id,
            'user_name' => "{$this->flaggedUser->firstname} {$this->flaggedUser->lastname}",
            'report_count' => $this->reportCount,
            'message' => "{$this->reportCount} signalements en 7 jours pour {$this->flaggedUser->firstname} {$this->flaggedUser->lastname}",
        ];
    }
}
