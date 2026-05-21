<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
            'currency' => $this->normalizeCurrencyForApi((string) $payload['currency']),
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

            throw new PaymentGatewayException(
                $this->parseInitiateErrorMessage($response),
                $this->resolveHttpStatusForException($response->status()),
            );
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
            Log::warning('GeniusPay verify unavailable', [
                'reference' => $externalReference,
                'http_status' => $response->status(),
                'response' => $response->json(),
            ]);

            // Keep the local payment pending — a 404/wrong reference or transient
            // API error must not poison the row as FAILED before the webhook lands.
            return [
                'status' => 'pending',
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
        $status = $this->extractGatewayStatus($data);
        $mappedStatus = match ($status) {
            'completed', 'complete', 'successful', 'success', 'paid' => 'success',
            'cancelled', 'canceled' => 'cancelled',
            'expired', 'failed' => 'failed',
            'pending', 'processing', 'in_progress', 'in-progress' => 'pending',
            default => 'pending',
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
    private function extractGatewayStatus(array $data): string
    {
        foreach (['status', 'payment_status', 'state'] as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return strtolower($value);
            }
        }

        $nested = $data['payment'] ?? $data['transaction'] ?? null;
        if (is_array($nested)) {
            foreach (['status', 'payment_status', 'state'] as $key) {
                $value = $nested[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return strtolower($value);
                }
            }
        }

        return 'pending';
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
        $configured = config('payment.geniuspay_payment_methods', []);
        if (is_array($configured) && isset($configured[$paymentMethod]) && is_string($configured[$paymentMethod])) {
            return $configured[$paymentMethod];
        }

        return match ($paymentMethod) {
            'orange_money' => 'orange_money',
            'mobile_money' => 'mtn_money',
            'flutterwave', 'card' => null,
            default => null,
        };
    }

    /**
     * GeniusPay merchant API accepts XOF, EUR, USD only (amounts are stored in XOF).
     * KeyHome ledger uses XAF for Cameroon — map 1:1 for the gateway request.
     */
    private function normalizeCurrencyForApi(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        return match ($normalized) {
            'XAF', 'XOF', '' => 'XOF',
            'EUR', 'USD' => $normalized,
            default => 'XOF',
        };
    }

    private function parseInitiateErrorMessage(Response $response): string
    {
        if ($response->status() >= 500) {
            return 'Passerelle de paiement indisponible.';
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        if (!is_array($json)) {
            return 'GeniusPay : échec de l\'initialisation du paiement.';
        }

        /** @var array<string, mixed>|null $errorBlock */
        $errorBlock = is_array($json['error'] ?? null) ? $json['error'] : null;
        /** @var array<string, array<int, string>|string>|null $fieldErrors */
        $fieldErrors = null;
        if (is_array($errorBlock['errors'] ?? null)) {
            $fieldErrors = $errorBlock['errors'];
        } elseif (is_array($json['errors'] ?? null)) {
            $fieldErrors = $json['errors'];
        }

        if (is_array($fieldErrors) && $fieldErrors !== []) {
            $messages = [];
            foreach ($fieldErrors as $field => $rules) {
                $label = $this->fieldLabelFr((string) $field);
                foreach ((array) $rules as $rule) {
                    $messages[] = $this->translateValidationRule((string) $rule, $label);
                }
            }

            if ($messages !== []) {
                return 'GeniusPay : '.implode(' ', array_values(array_unique($messages)));
            }
        }

        $rawMessage = (string) ($errorBlock['message'] ?? $json['message'] ?? '');

        if ($rawMessage !== '') {
            return 'GeniusPay : '.$this->translateKnownApiMessage($rawMessage);
        }

        return 'GeniusPay : échec de l\'initialisation du paiement.';
    }

    private function translateValidationRule(string $rule, string $fieldLabel): string
    {
        return match ($rule) {
            'validation.in' => "La valeur du champ « {$fieldLabel} » n'est pas acceptée par GeniusPay.",
            'validation.required' => "Le champ « {$fieldLabel} » est requis.",
            'validation.min' => "Le champ « {$fieldLabel} » est trop petit.",
            'validation.max' => "Le champ « {$fieldLabel} » est trop grand.",
            default => str_starts_with($rule, 'validation.')
                ? "Erreur de validation sur le champ « {$fieldLabel} »."
                : $rule,
        };
    }

    private function translateKnownApiMessage(string $message): string
    {
        return match ($message) {
            'validation.in' => 'Une ou plusieurs valeurs envoyées ne sont pas acceptées par GeniusPay (vérifiez la devise et le moyen de paiement).',
            default => $message,
        };
    }

    private function fieldLabelFr(string $field): string
    {
        $normalized = str_replace(['customer.', '_'], ['', ' '], $field);

        return match ($field) {
            'currency' => 'devise',
            'amount' => 'montant',
            'payment_method' => 'moyen de paiement',
            'customer.phone' => 'téléphone',
            'customer.email' => 'e-mail',
            'customer.name' => 'nom',
            'customer.country' => 'pays',
            default => trim($normalized) !== '' ? trim($normalized) : $field,
        };
    }

    private function resolveHttpStatusForException(int $status): int
    {
        if ($status >= 400 && $status < 600) {
            return $status;
        }

        return 502;
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
