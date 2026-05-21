<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notifies IndexNow-compatible search engines (Bing, Yandex, Seznam)
 * when ad URLs are created, updated, or deleted.
 *
 * Key file must be hosted at: https://{host}/{key}.txt
 * (place {key}.txt in keyhome-frontend-next/public/).
 *
 * Config keys:
 *   services.indexnow.key   — IndexNow API key (generate at bing.com/webmasters)
 *   services.indexnow.host  — canonical host, e.g. "keyhome.app"
 */
final readonly class IndexNowService
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function ping(string|array $urls): void
    {
        $key = config('services.indexnow.key');
        $host = config('services.indexnow.host');

        if (!$key || !$host) {
            return;
        }

        $urls = array_values(array_filter((array) $urls));

        if (empty($urls)) {
            return;
        }

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => "https://{$host}/{$key}.txt",
            'urlList' => $urls,
        ];

        try {
            $response = Http::timeout(5)->post(self::ENDPOINT, $payload);

            if ($response->failed()) {
                Log::warning('IndexNow ping failed', [
                    'status' => $response->status(),
                    'urls' => $urls,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('IndexNow ping exception', ['message' => $e->getMessage()]);
        }
    }
}
