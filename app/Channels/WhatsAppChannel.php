<?php

declare(strict_types=1);

namespace App\Channels;

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel for WhatsApp Business (Meta Cloud API).
 *
 * Notifications that support this channel must implement:
 *   toWhatsApp(object $notifiable): array{body: string, template?: string, params?: array<string, string>}
 *
 * Only sends if the notifiable has phone_is_whatsapp = true and the service is enabled.
 */
final readonly class WhatsAppChannel
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        if (!config('services.whatsapp.enabled', false)) {
            return;
        }

        $phone = $this->resolvePhone($notifiable);
        if (!$phone) {
            return;
        }

        /** @var array{body: string, template?: string, params?: array<string, string>} $message */
        $message = $notification->toWhatsApp($notifiable);

        if (isset($message['template'])) {
            $this->whatsapp->sendTemplate(
                $phone,
                $message['template'],
                'fr',
                $this->buildTemplateComponents($message['params'] ?? []),
            );
        } else {
            $this->whatsapp->sendText($phone, $message['body']);
        }
    }

    private function resolvePhone(mixed $notifiable): ?string
    {
        $phone = null;

        if ($notifiable instanceof User) {
            if (!(bool) $notifiable->phone_is_whatsapp) {
                return null;
            }

            $phone = $notifiable->phone_number;
        } elseif (method_exists($notifiable, 'routeNotificationForWhatsApp')) {
            $phone = $notifiable->routeNotificationForWhatsApp();
        }

        return filled($phone) ? (string) $phone : null;
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
