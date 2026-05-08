<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LeaseSignatureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class LeaseSignatureOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LeaseSignatureRequest $signatureRequest,
        public string $plainCode,
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
            ->subject('Votre code de signature KeyHome')
            ->greeting('Bonjour '.$this->signatureRequest->signer_name.' !')
            ->line('Voici votre code à saisir pour signer ou refuser le contrat de bail :')
            ->line($this->plainCode)
            ->line('Ce code est valable 15 minutes. Ne le partagez avec personne.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'signature_request_id' => $this->signatureRequest->id,
        ];
    }
}
