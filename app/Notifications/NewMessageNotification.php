<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) + email notification for a new chat message.
 *
 * Email is sent only if the user hasn't read the message within 5 minutes.
 */
final class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Message $message) {}

    /** @return array<string> */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type'              => 'chat_message',
            'conversation_uuid' => $this->message->conversation_id,
            'sender_id'         => $this->message->sender_id,
            'preview'           => $this->message->decrypted_body !== null
                ? mb_substr($this->message->decrypted_body, 0, 80)
                : '📎 Pièce jointe',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sender  = $this->message->sender;
        $name    = $sender ? trim("{$sender->firstname} {$sender->lastname}") : 'Quelqu\'un';
        $preview = $this->message->decrypted_body !== null
            ? mb_substr($this->message->decrypted_body, 0, 100)
            : '📎 Pièce jointe';

        return (new MailMessage())
            ->subject("Nouveau message de {$name}")
            ->greeting("Bonjour !")
            ->line("{$name} vous a envoyé un message sur KeyHome :")
            ->line("\"{$preview}\"")
            ->action('Voir le message', url("/messages/{$this->message->conversation_id}"))
            ->line('Répondez rapidement pour ne pas faire attendre votre interlocuteur.');
    }
}
