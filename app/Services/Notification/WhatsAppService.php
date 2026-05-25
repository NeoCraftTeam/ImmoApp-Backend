<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business Cloud API service.
 *
 * Sends template messages via Meta Graph API v19.0.
 * Requires WHATSAPP_ENABLED=true, WHATSAPP_TOKEN, and WHATSAPP_PHONE_NUMBER_ID.
 *
 * Template messages must be pre-approved in Meta Business Manager.
 * For simple text notifications, use the "text" message type (only possible
 * when the conversation window is open). In production, use approved templates.
 */
final class WhatsAppService
{
    /**
     * Send a free-form text message.
     * Only works within the 24-hour customer-initiated conversation window.
     */
    public function sendText(string $to, string $message): bool
    {
        if (!config('services.whatsapp.enabled', false)) {
            Log::debug('[WhatsAppService] WhatsApp disabled — skipping', compact('to'));

            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($to),
            'type' => 'text',
            'text' => ['body' => $message],
        ];

        return $this->dispatch($payload, $to);
    }

    /**
     * Send a pre-approved template message.
     *
     * @param  array<int, array<string, mixed>>  $components  Template variable components
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode = 'fr', array $components = []): bool
    {
        if (!config('services.whatsapp.enabled', false)) {
            Log::debug('[WhatsAppService] WhatsApp disabled — skipping', compact('to'));

            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        return $this->dispatch($payload, $to);
    }

    private function dispatch(array $payload, string $to): bool
    {
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = (string) config('services.whatsapp.api_version', 'v19.0');
        $baseUrl = rtrim((string) config('services.whatsapp.api_url', 'https://graph.facebook.com'), '/');

        $url = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken((string) config('services.whatsapp.token'))
                ->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::warning('[WhatsAppService] Send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('[WhatsAppService] Send exception', ['to' => $to, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Normalize phone number to E.164 without the leading +.
     * Meta API expects the number without the + prefix.
     */
    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone) ?? $phone;

        return ltrim($cleaned, '0');
    }
}
