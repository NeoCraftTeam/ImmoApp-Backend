<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LeaseSignatureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaseSignatureRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LeaseSignatureRequest $signatureRequest,
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
            ->subject('Vous avez un contrat à signer - KeyHome')
            ->greeting('Bonjour '.$this->signatureRequest->signer_name.' !')
            ->line('Vous avez reçu une demande de signature pour un contrat de bail.')
            ->line('Veuillez consulter et signer le contrat en cliquant sur le bouton ci-dessous.')
            ->action('Consulter et signer le contrat', config('app.frontend_url').'/sign/'.$this->signatureRequest->token)
            ->line('Cette demande expire le '.($this->signatureRequest->expires_at?->format('d/m/Y') ?? '—').'. Si vous n\'attendiez pas ce message, vous pouvez ignorer cet e-mail.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'signature_request_id' => $this->signatureRequest->id,
            'token' => $this->signatureRequest->token,
        ];
    }
}
