<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\FcmToken;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Send a Firebase FCM push notification to the message recipient.
 * Runs on the 'notifications' queue for priority delivery.
 * Invalid FCM tokens are automatically removed from the database.
 */
final class SendChatPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $recipientId,
        public readonly string $messageId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $message = Message::withTrashed()->find($this->messageId);

        if ($message === null) {
            return;
        }

        $tokens = FcmToken::where('user_id', $this->recipientId)
            ->pluck('token', 'id');

        if ($tokens->isEmpty()) {
            return;
        }

        $sender = $message->sender;
        $title = $sender ? trim("{$sender->firstname} {$sender->lastname}") : 'KeyHome';
        $body = $message->decrypted_body !== null
            ? mb_substr($message->decrypted_body, 0, 100)
            : '📎 Pièce jointe';

        // Build the deep-link URL for the conversation so notification taps
        // go directly to the right chat panel (owner vs client).
        $recipient = User::find($this->recipientId);
        $basePath = $recipient?->role === UserRole::AGENT ? '/owner/messages' : '/messages';
        $conversationUrl = $basePath.'/'.$message->conversation_id;

        $credentialsPath = (string) config('chat.firebase.credentials');
        if (!file_exists(storage_path('../'.ltrim($credentialsPath, '/')))) {
            $credentialsPath = storage_path('app/firebase-credentials.json');
        }

        if (!file_exists($credentialsPath)) {
            Log::warning('[FCM] Firebase credentials not found. Skipping push notification.', [
                'path' => $credentialsPath,
            ]);

            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $invalidTokenIds = [];

            foreach ($tokens as $tokenId => $token) {
                try {
                    $cloudMessage = CloudMessage::new()
                        ->withToken($token)
                        ->withNotification(Notification::create($title, $body))
                        ->withData([
                            'type' => 'chat_message',
                            'conversation_uuid' => $message->conversation_id,
                            'sender_id' => $message->sender_id,
                            'url' => $conversationUrl,
                        ]);

                    $messaging->send($cloudMessage);

                    FcmToken::where('id', $tokenId)->update(['last_used_at' => now()]);
                } catch (NotFound) {
                    $invalidTokenIds[] = $tokenId;
                } catch (\Throwable $e) {
                    Log::error('[FCM] Failed to send push notification', [
                        'token_id' => $tokenId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($invalidTokenIds)) {
                FcmToken::whereIn('id', $invalidTokenIds)->delete();
            }
        } catch (\Throwable $e) {
            Log::error('[FCM] Firebase initialization failed', ['error' => $e->getMessage()]);
        }
    }
}
