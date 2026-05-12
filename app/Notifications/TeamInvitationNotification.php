<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TeamInvitation $invitation,
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
            ->subject('Invitation à rejoindre l\'équipe - KeyHome')
            ->greeting('Bonjour !')
            ->line('Vous avez été invité(e) à rejoindre une agence sur KeyHome.')
            ->line('Rôle attribué : '.$this->invitation->role)
            ->action('Accepter l\'invitation', config('app.frontend_url').'/owner/team/accept/'.$this->invitation->token)
            ->line('Cette invitation expire le '.$this->invitation->expires_at->format('d/m/Y').'. Si vous n\'attendiez pas cette invitation, vous pouvez ignorer cet e-mail.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
