<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Send an email notification for a chat message if the recipient has not
 * already read it within the 5-minute delay window.
 *
 * Dispatched with a 5-minute delay from MessageService.
 * Runs on the 'emails' queue.
 */
final class SendOfflineEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly string $recipientId,
        public readonly string $messageId,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $message = Message::withTrashed()->find($this->messageId);

        if ($message === null) {
            return;
        }

        if ($message->trashed()) {
            return;
        }

        // Skip if the message has already been read (user opened the conversation)
        if ($message->status->value === 'read') {
            return;
        }

        $recipient = User::find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        $recipient->notify(new NewMessageNotification($message));
    }
}
