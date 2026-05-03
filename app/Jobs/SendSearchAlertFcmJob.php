<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ad;
use App\Models\FcmToken;
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
 * FCM push for search-alert matches (mobile / web tokens). Web Push browser path is handled
 * by {@see SearchAlertMatchNotification} when the user has no FCM registration.
 */
final class SendSearchAlertFcmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $recipientUserId,
        public readonly string $adId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $ad = Ad::query()->find($this->adId);

        if ($ad === null) {
            return;
        }

        $tokens = FcmToken::where('user_id', $this->recipientUserId)
            ->pluck('token', 'id');

        if ($tokens->isEmpty()) {
            return;
        }

        $title = 'Nouvelle annonce pour vous !';
        $body = $ad->title.' — '.number_format((float) ($ad->price ?? 0), 0, ',', ' ').' FCFA';
        $adUrl = rtrim((string) config('app.frontend_url'), '/').'/ads/'.rawurlencode((string) $ad->slug);

        $credentialsPath = (string) config('chat.firebase.credentials');
        if (!file_exists(storage_path('../'.ltrim($credentialsPath, '/')))) {
            $credentialsPath = storage_path('app/firebase-credentials.json');
        }

        if (!file_exists($credentialsPath)) {
            Log::warning('[FCM] Firebase credentials not found. Skipping search-alert push.', [
                'path' => $credentialsPath,
            ]);

            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $invalidTokenIds = [];

            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'ttl' => '86400s',
                'notification' => [
                    'sound' => 'default',
                    'click_action' => $adUrl,
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
                    ],
                ],
            ]);

            $webPushConfig = WebPushConfig::fromArray([
                'headers' => [
                    'Urgency' => 'high',
                    'TTL' => '86400',
                ],
                'notification' => [
                    'icon' => '/icons/icon-192x192.png',
                    'badge' => '/icons/icon-192x192.png',
                    'tag' => 'search-alert-'.$ad->id,
                    'renotify' => true,
                ],
                'fcm_options' => [
                    'link' => $adUrl,
                ],
            ]);

            foreach ($tokens as $tokenId => $token) {
                try {
                    $cloudMessage = CloudMessage::new()
                        ->withToken($token)
                        ->withNotification(Notification::create($title, $body))
                        ->withData([
                            'type' => 'search_alert_match',
                            'ad_id' => $ad->id,
                            'url' => $adUrl,
                        ])
                        ->withAndroidConfig($androidConfig)
                        ->withApnsConfig($apnsConfig)
                        ->withWebPushConfig($webPushConfig);

                    $messaging->send($cloudMessage);

                    FcmToken::where('id', $tokenId)->update(['last_used_at' => now()]);
                } catch (NotFound) {
                    $invalidTokenIds[] = $tokenId;
                } catch (\Throwable $e) {
                    Log::error('[FCM] Search-alert push failed', [
                        'token_id' => $tokenId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($invalidTokenIds !== []) {
                FcmToken::whereIn('id', $invalidTokenIds)->delete();
            }
        } catch (\Throwable $e) {
            Log::error('[FCM] Firebase init failed (search alert)', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
