<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DisputeMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Dispute $dispute,
        public DisputeMessage $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'dispute_message_received',
            'dispute_id' => $this->dispute->id,
            'reference' => $this->dispute->reference,
            'message_id' => $this->message->id,
            'message' => "Nouveau message dans le litige « {$this->dispute->reference} ».",
        ];
    }
}
