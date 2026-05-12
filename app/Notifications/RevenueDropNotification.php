<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RevenueDropNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('⚠ Alerte revenus — Baisse significative détectée')
            ->greeting('Alerte CEO')
            ->line('Les revenus de ce mois sont en baisse de plus de 20% par rapport au mois précédent.')
            ->line('Vérifiez les métriques détaillées dans le dashboard admin.')
            ->action('Voir le dashboard', url('/admin'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'revenue_drop',
            'message' => 'Les revenus mensuels sont en baisse de plus de 20% — action requise.',
        ];
    }
}
