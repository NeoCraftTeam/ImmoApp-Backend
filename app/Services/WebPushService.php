<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Service to send Web Push notifications via the Web Push protocol.
 *
 * Uses minishlink/web-push for proper VAPID authentication.
 * Reads VAPID config from config/webpush.php (published from the package).
 */
class WebPushService
{
    private ?WebPush $webPush = null;

    /**
     * Get or create the WebPush instance with VAPID auth.
     */
    private function getWebPush(): WebPush
    {
        if ($this->webPush === null) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ],
            ]);

            $this->webPush->setReuseVAPIDHeaders(true);
        }

        return $this->webPush;
    }

    /**
     * Send a push notification to all subscriptions for a given user.
     *
     * @param  array{title?: string, body: string, icon?: string, badge?: string, tag?: string, url?: string, actions?: array<int, array{action: string, title: string}>}  $payload
     */
    public function sendToUser(User $user, array $payload): int
    {
        $subscriptions = $user->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            /** @var PushSubscription $subscription */
            if ($this->sendToSubscription($subscription, $payload)) {
                $subscription->update(['last_used_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send a push notification to a specific subscription.
     *
     * @param  array{title?: string, body: string, icon?: string, badge?: string, tag?: string, url?: string, actions?: array<int, array{action: string, title: string}>}  $payload
     */
    public function sendToSubscription(PushSubscription $subscription, array $payload): bool
    {
        $payload = array_merge([
            'title' => 'KeyHome',
            'icon' => '/pwa/icons/icon-192x192.png',
            'badge' => '/pwa/icons/icon-72x72.png',
            'tag' => 'keyhome-'.($payload['tag'] ?? 'default'),
            'data' => ['url' => $payload['url'] ?? '/admin'],
        ], $payload);

        try {
            $webPushSubscription = Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
            ]);

            $report = $this->getWebPush()->sendOneNotification(
                $webPushSubscription,
                json_encode($payload) ?: ''
            );

            if ($report->isSuccess()) {
                return true;
            }

            if ($report->isSubscriptionExpired()) {
                $subscription->delete();
                Log::info('[WebPush] Removed expired subscription', ['endpoint' => $subscription->endpoint]);

                return false;
            }

            Log::warning('[WebPush] Failed to send', [
                'endpoint' => $subscription->endpoint,
                'reason' => $report->getReason(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('[WebPush] Exception sending notification', [
                'endpoint' => $subscription->endpoint,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Broadcast a push notification to all active subscriptions.
     *
     * @param  array{title?: string, body: string, icon?: string, badge?: string, tag?: string, url?: string}  $payload
     */
    public function broadcast(array $payload): int
    {
        $sent = 0;

        PushSubscription::query()
            ->with('subscribable')
            ->chunk(100, function ($subscriptions) use ($payload, &$sent): void {
                foreach ($subscriptions as $subscription) {
                    if ($this->sendToSubscription($subscription, $payload)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Cleanup expired or invalid subscriptions.
     */
    public function cleanupStaleSubscriptions(int $daysInactive = 30): int
    {
        return PushSubscription::query()
            ->where(function ($query) use ($daysInactive): void {
                $query->where('last_used_at', '<', now()->subDays($daysInactive))
                    ->orWhereNull('last_used_at');
            })
            ->where('created_at', '<', now()->subDays($daysInactive))
            ->delete();
    }
}
