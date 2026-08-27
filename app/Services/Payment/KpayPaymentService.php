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
 * Kpay merchant API (hosted GATEWAY checkout + mobile money, pawaPay-backed).
 *
 * @see https://kpay.site/documentation
 */
final readonly class KpayPaymentService implements PaymentGatewayInterface
{
    private string $apiKey;

    private string $apiSecret;

    private string $baseUrl;

    private string $webhookSecret;

    public function __construct()
    {
        $this->apiKey = (string) config('payment.gateways.kpay.api_key', '');
        $this->apiSecret = (string) config('payment.gateways.kpay.api_secret', '');
        $this->baseUrl = rtrim((string) config('payment.gateways.kpay.base_url', 'https://admin.kpay.site'), '/');
        $this->webhookSecret = (string) config('payment.gateways.kpay.webhook_secret', '');
    }

    /**
     * {@inheritDoc}
     *
     * Uses Kpay's hosted GATEWAY mode: the customer picks their own Mobile
     * Money operator and enters their number on Kpay's payment page. We
     * therefore never send `provider` / `phoneNumber` / `customerName` —
     * only `amount`, `currency` (mandatory in GATEWAY mode so the amount shown
     * on the hosted page is unambiguous), `externalId` (our tx_ref, used for
     * idempotency and matched back verbatim in the webhook + verify payloads),
     * `returnUrl` and `cancelUrl`.
     */
    public function initiate(array $payload): array
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $meta['tx_ref'] = $payload['tx_ref'];

        // GATEWAY mode rejects the init request when `currency` is missing;
        // fall back to the ledger currency if an upstream caller ever passes
        // it blank so we never resurface the "champ currency obligatoire" 422.
        $currency = strtoupper((string) $payload['currency']);
        if ($currency === '') {
            $currency = strtoupper((string) config('payment.default_currency', 'XAF'));
        }

        $body = [
            'amount' => (int) round((float) $payload['amount']),
            'currency' => $currency,
            'externalId' => (string) $payload['tx_ref'],
            'returnUrl' => (string) $payload['redirect_url'],
            'cancelUrl' => (string) $payload['redirect_url'],
            'description' => (string) ($payload['description'] ?? 'Paiement KeyHome'),
            'metadata' => $meta,
        ];

        $email = trim((string) $payload['email']);
        if ($email !== '') {
            $body['customerEmail'] = $email;
        }

        $response = $this->client()->post('/api/v1/payments/init', $body);

        if ($response->failed()) {
            Log::error('Kpay initiate failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new PaymentGatewayException(
                $this->parseInitiateErrorMessage($response),
                $this->resolveHttpStatusForException($response->status()),
            );
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->json();
        $link = (string) ($data['gatewayUrl'] ?? '');

        if ($link === '') {
            throw new PaymentGatewayException('Kpay : aucune URL de paiement retournée.', 502);
        }

        return [
            'link' => $link,
            'tx_ref' => (string) $payload['tx_ref'],
            'status' => 'pending',
            'gateway' => $this->getName(),
            'raw' => $this->withKhPaymentTrace($data),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * `$externalReference` is the Kpay `id` (e.g. `pay_abc123`) captured at
     * initiate time — {@see PaymentService::resolveKpayVerifyReference()}.
     */
    public function verify(string $externalReference): array
    {
        $response = $this->client()->get('/api/v1/payments/'.$externalReference);

        if ($response->failed()) {
            Log::warning('Kpay verify unavailable', [
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
        $data = (array) $response->json();

        return $this->normaliseTransaction($data);
    }

    /**
     * {@inheritDoc}
     */
    public function handleWebhook(array $payload, array $headers, ?string $rawBody = null): array
    {
        $signature = (string) (
            $headers['X-KPAY-Signature']
            ?? $headers['x-kpay-signature']
            ?? $headers['HTTP_X_KPAY_SIGNATURE']
            ?? ''
        );
        $event = (string) (
            $headers['X-KPAY-Event']
            ?? $headers['x-kpay-event']
            ?? $headers['HTTP_X_KPAY_EVENT']
            ?? ($payload['event'] ?? '')
        );

        if ($this->webhookSecret === '' || $signature === '') {
            throw new InvalidWebhookSignatureException('Invalid webhook signature.');
        }

        // Kpay signs the exact raw bytes received (HMAC-SHA256, hex). Re-encoding
        // the parsed `$payload` can produce different bytes (key order, unicode,
        // slash escaping) — always prefer `$rawBody` when available.
        $body = (is_string($rawBody) && $rawBody !== '' ? $rawBody : json_encode($payload)) ?: '';
        $expected = hash_hmac('sha256', $body, $this->webhookSecret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('Kpay webhook: invalid signature', [
                'ip' => request()->ip(),
            ]);

            throw new InvalidWebhookSignatureException('Invalid webhook signature.');
        }

        // Only deposit events (`payment.*`) map to the `payments` ledger.
        // Withdrawal (`payout.*`) and refund (`refund.*`) events aren't
        // modelled yet — acknowledge without side effects.
        if (!str_starts_with($event, 'payment.')) {
            return [
                'event' => $event,
                'event_id' => null,
                'tx_ref' => '',
                'status' => 'ignored',
                'amount' => 0.0,
                'currency' => '',
                'payment_method' => null,
                'raw' => $payload,
            ];
        }

        $txRef = (string) ($payload['externalId'] ?? '');
        $normalised = $this->normaliseTransaction($payload);

        // Kpay does not expose a stable event identifier. The
        // (event-name + tx_ref + paymentId + signature) tuple is unique
        // per real provider attempt — a retry replays the same signature.
        $eventId = 'kpay_'.hash(
            'sha256',
            $event.'|'.$txRef.'|'.(string) ($payload['paymentId'] ?? '').'|'.$signature,
        );

        return [
            'event' => $event,
            'event_id' => $eventId,
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
        return 'kpay';
    }

    /**
     * {@inheritDoc}
     */
    public function refund(string $gatewayTransactionId, ?float $amount = null): array
    {
        throw new PaymentGatewayException(
            'Kpay : les remboursements automatiques ne sont pas disponibles via l\'API marchand. Traitez le remboursement depuis le tableau de bord Kpay.',
            501,
        );
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'X-Secret-Key' => $this->apiSecret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    /**
     * @param  array<string, mixed>  $data  The Payment object (verify/webhook) or the
     *                                      GATEWAY-mode init response.
     * @return array{status: string, amount: float, currency: string, payment_method: string|null, paid_at: string|null, raw: array<string, mixed>}
     */
    private function normaliseTransaction(array $data): array
    {
        $status = strtoupper((string) ($data['status'] ?? 'PENDING'));
        $mappedStatus = match ($status) {
            'COMPLETED' => 'success',
            'CANCELLED' => 'cancelled',
            'FAILED' => 'failed',
            'PENDING', 'PROCESSING' => 'pending',
            default => 'pending',
        };

        if (!in_array($status, ['COMPLETED', 'CANCELLED', 'FAILED', 'PENDING', 'PROCESSING'], true)) {
            Log::warning('Kpay: unmapped transaction status, treated as pending', [
                'status' => $status,
                'reference' => $data['reference'] ?? ($data['id'] ?? null),
            ]);
        }

        $paidAt = null;
        if ($mappedStatus === 'success') {
            $paidAt = (string) ($data['completedAt'] ?? now()->toISOString());
        }

        return [
            'status' => $mappedStatus,
            // Kpay's webhook payload omits `currency` entirely; the operator
            // country/currency is only surfaced by the polling GET endpoint.
            // Default to KeyHome's ledger currency (Cameroon launch market).
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? config('payment.default_currency', 'XAF')),
            'payment_method' => $this->resolvePaymentMethod($data),
            'paid_at' => $paidAt,
            'raw' => $this->withKhPaymentTrace($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePaymentMethod(array $data): ?string
    {
        $provider = strtolower((string) ($data['provider'] ?? ''));

        return match (true) {
            str_contains($provider, 'orange') => PaymentMethod::ORANGE_MONEY->value,
            $provider !== '' => PaymentMethod::MOBILE_MONEY->value,
            default => null,
        };
    }

    private function detailLabelForProvider(?string $provider): ?string
    {
        if ($provider === null || $provider === '') {
            return null;
        }

        $p = strtolower($provider);

        return match (true) {
            str_contains($p, 'mtn') => 'MTN Mobile Money',
            str_contains($p, 'orange') => 'Orange Money',
            str_contains($p, 'moov') => 'Moov Money',
            str_contains($p, 'airtel') => 'Airtel Money',
            str_contains($p, 'vodacom'), str_contains($p, 'mpesa') => 'M-Pesa',
            str_contains($p, 'free') => 'Free Money',
            str_contains($p, 'zamtel') => 'Zamtel Money',
            default => ucfirst(str_replace('_', ' ', $provider)),
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
            return 'Kpay : échec de l\'initialisation du paiement.';
        }

        $message = (string) ($json['message'] ?? '');

        return $message !== '' ? 'Kpay : '.$message : 'Kpay : échec de l\'initialisation du paiement.';
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
     * @return array<string, mixed>
     */
    private function withKhPaymentTrace(array $data): array
    {
        $resolved = $this->resolvePaymentMethod($data);
        $enum = $resolved !== null ? PaymentMethod::tryFrom($resolved) : null;
        $provider = (string) ($data['provider'] ?? '');

        return array_merge($data, [
            'kpay_id' => $data['id'] ?? $data['paymentId'] ?? null,
            'kh_payment_trace' => [
                'label_fr' => 'Mobile',
                'detail_fr' => $this->detailLabelForProvider($provider !== '' ? $provider : null)
                    ?? ($enum === PaymentMethod::ORANGE_MONEY ? 'Orange Money' : ($enum === PaymentMethod::MOBILE_MONEY ? 'MTN Mobile Money' : null)),
                'kpay_provider' => $provider,
                'instrument_family' => 'mobile_money',
            ],
        ]);
    }
}
