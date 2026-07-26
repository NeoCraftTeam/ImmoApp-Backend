<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS dispatch service.
 *
 * Sends via Orange SMS API (primary) with automatic Twilio fallback.
 * Both providers are enabled when their credentials are configured.
 * Does nothing (logs a warning) when SMS_ENABLED=false or no provider is configured.
 */
final class SmsService
{
    /**
     * Send a plain-text SMS to the given E.164 phone number.
     *
     * Returns true on success, false on failure.
     */
    public function send(string $to, string $message): bool
    {
        if (!config('services.sms.enabled', false)) {
            Log::debug('[SmsService] SMS disabled — skipping', compact('to'));

            return false;
        }

        $provider = (string) config('services.sms.provider', 'orange');

        if ($provider === 'orange' && $this->hasOrangeCredentials()) {
            if ($this->sendViaOrange($to, $message)) {
                return true;
            }

            if ($this->hasTwilioCredentials()) {
                Log::warning('[SmsService] Orange failed, falling back to Twilio', compact('to'));

                return $this->sendViaTwilio($to, $message);
            }

            return false;
        }

        if ($this->hasTwilioCredentials()) {
            return $this->sendViaTwilio($to, $message);
        }

        Log::warning('[SmsService] No SMS provider configured', compact('to'));

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Orange SMS API (OAuth2 client-credentials + SMPP-style REST)
    // ─────────────────────────────────────────────────────────────────────────

    private function hasOrangeCredentials(): bool
    {
        return filled(config('services.sms.orange.client_id'))
            && filled(config('services.sms.orange.client_secret'));
    }

    private function orangeAccessToken(): ?string
    {
        return Cache::remember('orange_sms_token', 3000, function (): ?string {
            try {
                $response = Http::asForm()->post(
                    (string) config('services.sms.orange.token_url'),
                    [
                        'grant_type' => 'client_credentials',
                        'client_id' => config('services.sms.orange.client_id'),
                        'client_secret' => config('services.sms.orange.client_secret'),
                    ]
                );

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::warning('[SmsService] Orange token request failed', ['status' => $response->status()]);
            } catch (ConnectionException $e) {
                Log::warning('[SmsService] Orange token request exception', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    private function sendViaOrange(string $to, string $message): bool
    {
        $token = $this->orangeAccessToken();
        if (!$token) {
            return false;
        }

        $sender = (string) config('services.sms.orange.sender_address');
        $encodedSender = rawurlencode($sender);
        $apiUrl = str_replace('{sender}', $encodedSender, (string) config('services.sms.orange.api_url'));

        try {
            $response = Http::withToken($token)
                ->post($apiUrl, [
                    'outboundSMSMessageRequest' => [
                        'address' => 'tel:'.$to,
                        'senderAddress' => $sender,
                        'outboundSMSTextMessage' => ['message' => $message],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('[SmsService] Orange send failed', ['to' => $to, 'status' => $response->status(), 'body' => $response->body()]);
        } catch (ConnectionException $e) {
            Log::warning('[SmsService] Orange send exception', ['to' => $to, 'error' => $e->getMessage()]);
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Twilio REST API
    // ─────────────────────────────────────────────────────────────────────────

    private function hasTwilioCredentials(): bool
    {
        return filled(config('services.sms.twilio.sid'))
            && filled(config('services.sms.twilio.token'))
            && filled(config('services.sms.twilio.from'));
    }

    private function sendViaTwilio(string $to, string $message): bool
    {
        $sid = (string) config('services.sms.twilio.sid');
        $url = str_replace('{sid}', $sid, (string) config('services.sms.twilio.api_url'));

        try {
            $response = Http::withBasicAuth($sid, (string) config('services.sms.twilio.token'))
                ->asForm()
                ->post($url, [
                    'From' => config('services.sms.twilio.from'),
                    'To' => $to,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('[SmsService] Twilio send failed', ['to' => $to, 'status' => $response->status(), 'body' => $response->body()]);
        } catch (ConnectionException $e) {
            Log::warning('[SmsService] Twilio send exception', ['to' => $to, 'error' => $e->getMessage()]);
        }

        return false;
    }
}
