<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Contracts\FirebaseMessagingResolverInterface;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

/**
 * Lazily builds and memoises a single Firebase Cloud Messaging client.
 *
 * FCM push jobs (chat notifications, search alerts) run on long-lived
 * `queue:work` workers at high volume. Constructing a fresh {@see Factory}
 * per job re-parses the service-account JSON, rebuilds the HTTP stack and
 * re-fetches a Google OAuth token every time. Bound as a container singleton,
 * this keeps one {@see Messaging} instance — and its in-memory auth-token
 * cache — alive for the worker's lifetime, so every job after the first
 * reuses it instead of paying that cost again.
 *
 * Returns null (rather than throwing) when credentials are absent, so callers
 * degrade gracefully exactly as they did when resolving the client inline.
 */
final class FirebaseMessagingFactory implements FirebaseMessagingResolverInterface
{
    private ?Messaging $messaging = null;

    private bool $resolved = false;

    public function make(): ?Messaging
    {
        if ($this->resolved) {
            return $this->messaging;
        }

        $credentialsPath = $this->resolveCredentialsPath();

        if ($credentialsPath === null) {
            $this->resolved = true;

            Log::warning('[FCM] Firebase credentials not found. Skipping push notification.', [
                'path' => storage_path('app/firebase-credentials.json'),
            ]);

            return null;
        }

        $this->messaging = (new Factory)->withServiceAccount($credentialsPath)->createMessaging();
        $this->resolved = true;

        return $this->messaging;
    }

    private function resolveCredentialsPath(): ?string
    {
        $credentialsPath = (string) config('chat.firebase.credentials');

        if (!file_exists(storage_path('../'.ltrim($credentialsPath, '/')))) {
            $credentialsPath = storage_path('app/firebase-credentials.json');
        }

        if (!file_exists($credentialsPath)) {
            return null;
        }

        return $credentialsPath;
    }
}
