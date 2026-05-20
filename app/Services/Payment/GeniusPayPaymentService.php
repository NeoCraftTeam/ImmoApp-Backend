<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeniusPay merchant API (hosted checkout + mobile money).
 *
 * @see https://pay.genius.ci/docs/api
 */
final readonly class GeniusPayPaymentService implements PaymentGatewayInterface
{
    private const int WEBHOOK_TOLERANCE_SECONDS = 300;

    private string $apiKey;

    private string $apiSecret;

    private string $baseUrl;

    private string $webhookSecret;

    public function __construct()
    {
        $this->apiKey = (string) config('payment.gateways.geniuspay.api_key', '');
        $this->apiSecret = (string) config('payment.gateways.geniuspay.api_secret', '');
        $this->baseUrl = rtrim((string) config('payment.gateways.geniuspay.base_url', 'https://pay.genius.ci/api/v1/merchant'), '/');
        $this->webhookSecret = (string) config('payment.gateways.geniuspay.webhook_secret', '');
    }

    /**
     * {@inheritDoc}
     */
    public function initiate(array $payload): array
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $meta['tx_ref'] = $payload['tx_ref'];

        $body = [
            'amount' => (int) round((float) $payload['amount']),
            'currency' => (string) $payload['currency'],
            'description' => (string) ($payload['description'] ?? 'Paiement KeyHome'),
            'customer' => [
                'name' => (string) $payload['name'],
                'email' => (string) $payload['email'],
                'phone' => (string) ($payload['phone'] ?? ''),
                'country' => (string) config('payment.gateways.geniuspay.default_country', 'CM'),
            ],
            'success_url' => (string) $payload['redirect_url'],
            'error_url' => (string) $payload['redirect_url'],
            'metadata' => $meta,
        ];

        $paymentMethod = $this->resolveGeniusPaymentMethod((string) ($payload['payment_method'] ?? ''));
        if ($paymentMethod !== null) {
            $body['payment_method'] = $paymentMethod;
        }

        $response = $this->client()->post('/payments', $body);

        if ($response->failed() || $response->json('success') !== true) {
            Log::error('GeniusPay initiate failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            $message = $response->status() >= 500
                ? 'Passerelle de paiement indisponible'
                : 'GeniusPay : '.($response->json('error.message') ?? $response->json('message') ?? 'Initialisation du paiement échouée.');

            throw new PaymentGatewayException($message, $response->status());
        }

        /** @var array<string, mixed> $data */
        $data = $response->json('data', []);
        $link = (string) ($data['checkout_url'] ?? $data['payment_url'] ?? '');
        $reference = (string) ($data['reference'] ?? '');

        if ($link === '') {
            throw new PaymentGatewayException('GeniusPay : aucune URL de paiement retournée.', 502);
        }

        return [
            'link' => $link,
            'tx_ref' => (string) $payload['tx_ref'],
            'status' => 'pending',
            'gateway' => $this->getName(),
            'raw' => $this->withKhPaymentTrace($data, $reference),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $externalReference): array
    {
        $response = $this->client()->get('/payments/'.$externalReference);

        if ($response->failed() || $response->json('success') !== true) {
            Log::warning('GeniusPay verify failed', [
                'reference' => $externalReference,
                'response' => $response->json(),
            ]);

            return [
                'status' => 'failed',
                'amount' => 0.0,
                'currency' => '',
                'payment_method' => null,
                'paid_at' => null,
                'raw' => $response->json() ?? [],
            ];
        }

        /** @var array<string, mixed> $data */
        $data = $response->json('data', []);

        return $this->normaliseTransaction($data);
    }

    /**
     * {@inheritDoc}
     */
    public function handleWebhook(array $payload, array $headers): array
    {
        $signature = (string) (
            $headers['X-Webhook-Signature']
            ?? $headers['x-webhook-signature']
            ?? $headers['HTTP_X_WEBHOOK_SIGNATURE']
            ?? ''
        );
        $timestamp = (string) (
            $headers['X-Webhook-Timestamp']
            ?? $headers['x-webhook-timestamp']
            ?? $headers['HTTP_X_WEBHOOK_TIMESTAMP']
            ?? ''
        );
        $event = (string) (
            $headers['X-Webhook-Event']
            ?? $headers['x-webhook-event']
            ?? $headers['HTTP_X_WEBHOOK_EVENT']
            ?? ($payload['event'] ?? '')
        );

        if ($this->webhookSecret === '' || $signature === '' || $timestamp === '') {
            throw new InvalidWebhookSignatureException('Invalid webhook signature.');
        }

        if (abs(time() - (int) $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            throw new InvalidWebhookSignatureException('Webhook timestamp too old.');
        }

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encodedPayload === false) {
            throw new InvalidWebhookSignatureException('Invalid webhook payload.');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$encodedPayload, $this->webhookSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('GeniusPay webhook: invalid signature', [
                'ip' => request()->ip(),
            ]);

            throw new InvalidWebhookSignatureException('Invalid webhook signature.');
        }

        if ($event === 'webhook.test') {
            return [
                'event' => $event,
                'tx_ref' => '',
                'status' => 'ignored',
                'amount' => 0.0,
                'currency' => '',
                'payment_method' => null,
                'raw' => $payload,
            ];
        }

        /** @var array<string, mixed> $data */
        $data = (array) ($payload['data'] ?? []);
        $txRef = $this->resolveTxRefFromPayload($data);
        $normalised = $this->normaliseTransaction($data);

        return [
            'event' => $event,
            'tx_ref' => $txRef,
            'status' => $normalised['status'],
            'amount' => $normalised['amount'],
            'currency' => $normalised['currency'],
            'payment_method' => $normalised['payment_method'],
            'raw' => $normalised['raw'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'geniuspay';
    }

    /**
     * {@inheritDoc}
     */
    public function refund(string $gatewayTransactionId, ?float $amount = null): array
    {
        throw new PaymentGatewayException(
            'GeniusPay : les remboursements automatiques ne sont pas disponibles via l\'API marchand. Traitez le remboursement depuis le tableau de bord GeniusPay.',
            501,
        );
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, amount: float, currency: string, payment_method: string|null, paid_at: string|null, raw: array<string, mixed>}
     */
    private function normaliseTransaction(array $data): array
    {
        $status = strtolower((string) ($data['status'] ?? 'failed'));
        $mappedStatus = match ($status) {
            'completed', 'successful', 'success' => 'success',
            'cancelled', 'canceled' => 'cancelled',
            'expired', 'failed' => 'failed',
            default => $status,
        };

        $paidAt = null;
        if ($mappedStatus === 'success') {
            $paidAt = (string) ($data['completed_at'] ?? $data['created_at'] ?? now()->toISOString());
        }

        $raw = $this->withKhPaymentTrace(
            $data,
            (string) ($data['reference'] ?? ''),
        );

        return [
            'status' => $mappedStatus,
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? config('payment.default_currency', 'XAF')),
            'payment_method' => $this->resolvePaymentMethod($data),
            'paid_at' => $paidAt,
            'raw' => $raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTxRefFromPayload(array $data): string
    {
        $metadata = $data['metadata'] ?? [];
        if (is_array($metadata) && isset($metadata['tx_ref']) && is_string($metadata['tx_ref'])) {
            return $metadata['tx_ref'];
        }

        return '';
    }

    private function resolveGeniusPaymentMethod(string $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            'orange_money' => 'orange_money',
            'mobile_money' => 'mtn_money',
            'flutterwave' => null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePaymentMethod(array $data): ?string
    {
        $method = strtolower((string) ($data['payment_method'] ?? $data['payment_provider'] ?? $data['provider'] ?? ''));

        return match (true) {
            str_contains($method, 'orange') => PaymentMethod::ORANGE_MONEY->value,
            str_contains($method, 'mtn') => PaymentMethod::MOBILE_MONEY->value,
            str_contains($method, 'mobile') => PaymentMethod::MOBILE_MONEY->value,
            str_contains($method, 'wave') => PaymentMethod::MOBILE_MONEY->value,
            str_contains($method, 'card') => PaymentMethod::CARD->value,
            $method !== '' => $method,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withKhPaymentTrace(array $data, string $geniusReference): array
    {
        $resolved = $this->resolvePaymentMethod($data);
        $enum = $resolved !== null ? PaymentMethod::tryFrom($resolved) : null;
        $provider = strtolower((string) ($data['payment_provider'] ?? $data['provider'] ?? $data['gateway'] ?? ''));

        $detail = match (true) {
            str_contains($provider, 'orange') => 'Orange Cameroun',
            str_contains($provider, 'mtn') => 'MTN Cameroun',
            str_contains($provider, 'wave') => 'Wave',
            $provider !== '' => ucfirst(trim($provider)),
            default => null,
        };

        return array_merge($data, [
            'genius_reference' => $geniusReference !== '' ? $geniusReference : ($data['reference'] ?? null),
            'kh_payment_trace' => [
                'label_fr' => $enum?->label() ?? 'GeniusPay',
                'detail_fr' => $detail,
                'geniuspay_payment_method' => (string) ($data['payment_method'] ?? ''),
            ],
        ]);
    }
}
