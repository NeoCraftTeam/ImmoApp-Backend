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
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;

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

        if ($message->trashed()) {
            return;
        }

        $tokens = FcmToken::where('user_id', $this->recipientId)
            ->pluck('token', 'id');

        if ($tokens->isEmpty()) {
            return;
        }

        $sender = $message->sender;
        $title = $sender ? trim("{$sender->firstname} {$sender->lastname}") : 'KeyHome';

        $body = $message->is_client_sealed
            ? '🔐 Message sécurisé'
            : ($message->decrypted_body !== null
                ? mb_substr($message->decrypted_body, 0, 100)
                : '📎 Pièce jointe');

        // Build the deep-link URL for the conversation so notification taps
        // go directly to the right chat panel (owner vs client).
        // ADMIN users use the owner panel as well — only CUSTOMER goes client-side.
        $recipient = User::find($this->recipientId);
        $isOwnerPanel = $recipient !== null
            && in_array($recipient->role, [UserRole::AGENT, UserRole::ADMIN], true);
        $basePath = $isOwnerPanel ? '/owner/messages' : '/messages';
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

            // Per-platform priority + lifecycle config:
            //   - Android: priority=high so the device wakes from doze even with the
            //     screen off (default 'normal' is heavily throttled in deep sleep).
            //   - iOS (APNs): apns-priority=10 + alert content so PWA push is
            //     delivered in real time even when the user has the screen locked.
            //   - WebPush: Urgency: high so browsers (Chromium / Firefox) prioritise
            //     delivery over best-effort batching.
            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'ttl' => '86400s',
                'notification' => [
                    'sound' => 'default',
                    'click_action' => $conversationUrl,
                ],
            ]);

            $apnsConfig = ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                    'apns-push-type' => 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => ['title' => $title, 'body' => $body],
                        'sound' => 'default',
                        'mutable-content' => 1,
                    ],
                ],
            ]);

            $webPushConfig = WebPushConfig::fromArray([
                'headers' => [
                    'Urgency' => 'high',
                    'TTL' => '86400',
                ],
                'notification' => [
                    'title' => $title,
                    'icon' => '/icons/icon-192x192.png',
                    'badge' => '/icons/icon-192x192.png',
                    'tag' => 'chat-'.$message->conversation_id,
                    'renotify' => true,
                    'requireInteraction' => false,
                ],
                'fcm_options' => [
                    'link' => $conversationUrl,
                ],
            ]);

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
                        ])
                        ->withAndroidConfig($androidConfig)
                        ->withApnsConfig($apnsConfig)
                        ->withWebPushConfig($webPushConfig);

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
