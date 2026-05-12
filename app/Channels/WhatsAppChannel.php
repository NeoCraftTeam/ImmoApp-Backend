<?php

declare(strict_types=1);

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    /**
     * Send the given notification via WhatsApp Business API.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->phone ?? null;

        if (!$phone || !($notifiable->phone_is_whatsapp ?? false)) {
            return;
        }

        if (!config('services.whatsapp.enabled')) {
            Log::info('WhatsApp notification skipped (disabled)', [
                'notification' => $notification::class,
                'phone' => $phone,
            ]);

            return;
        }

        /** @var array{body: string, template?: string, params?: array<string, string>} $message */
        $message = $notification->toWhatsApp($notifiable);

        $this->sendMessage($phone, $message);
    }

    /**
     * @param  array{body: string, template?: string, params?: array<string, string>}  $message
     */
    private function sendMessage(string $phone, array $message): void
    {
        $phone = $this->formatPhone($phone);

        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (!$token || !$phoneNumberId) {
            Log::warning('WhatsApp credentials not configured');

            return;
        }

        try {
            $payload = isset($message['template'])
                ? [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $message['template'],
                        'language' => ['code' => 'fr'],
                        'components' => $this->buildTemplateComponents($message['params'] ?? []),
                    ],
                ]
                : [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $message['body']],
                ];

            Http::withToken($token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", $payload)
                ->throw();
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with((string) $phone, '6') && strlen((string) $phone) === 9) {
            return '237'.$phone;
        }

        return ltrim((string) $phone, '+');
    }

    /**
     * @param  array<string, string>  $params
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateComponents(array $params): array
    {
        if (empty($params)) {
            return [];
        }

        $parameters = [];
        foreach ($params as $value) {
            $parameters[] = ['type' => 'text', 'text' => $value];
        }

        return [['type' => 'body', 'parameters' => $parameters]];
    }
}
