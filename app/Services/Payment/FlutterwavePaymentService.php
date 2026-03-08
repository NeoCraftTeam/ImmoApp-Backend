<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave payment gateway implementation using the v3 Standard Checkout.
 *
 * Flow:
 *  1. POST /v3/payments         → returns hosted checkout link  (initiate)
 *  2. GET  /v3/transactions/verify_by_reference?tx_ref={ref}  (verify)
 *  3. POST to our /webhooks/flutterwave  — validated via verif-hash header  (webhook)
 */
final readonly class FlutterwavePaymentService implements PaymentGatewayInterface
{
    private string $secretKey;

    private string $baseUrl;

    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = (string) config('payment.gateways.flutterwave.secret_key', '');
        $this->baseUrl = rtrim((string) config('payment.gateways.flutterwave.base_url', 'https://api.flutterwave.com/v3'), '/');
        $this->webhookSecret = (string) config('payment.gateways.flutterwave.webhook_secret', '');
    }

    /**
     * Initiate a Flutterwave Standard Checkout payment.
     *
     * {@inheritDoc}
     */
    public function initiate(array $payload): array
    {
        $paymentOptions = config(
            'payment.flutterwave_payment_options.'.(string) ($payload['payment_method'] ?? 'flutterwave'),
            'mobilemoneycameroon,card'
        );

        $body = [
            'tx_ref' => $payload['tx_ref'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'payment_options' => $paymentOptions,
            'redirect_url' => $payload['redirect_url'],
            'customer' => [
                'email' => $payload['email'],
                'phonenumber' => $payload['phone'],
                'name' => $payload['name'],
            ],
            'customizations' => [
                'title' => config('app.name', 'KeyHome'),
                'description' => $payload['description'] ?? 'Paiement KeyHome',
                'logo' => config('payment.gateways.flutterwave.logo', ''),
            ],
            'meta' => $payload['meta'] ?? [],
        ];

        $response = $this->client()->post('/payments', $body);

        if ($response->failed() || ($response->json('status') !== 'success')) {
            Log::error('Flutterwave initiate failed', [
                'body' => $body,
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            $message = $response->status() >= 500
                ? 'Gateway indisponible'
                : 'Flutterwave: '.($response->json('message') ?? 'Initialisation du paiement échouée.');

            throw new PaymentGatewayException($message, $response->status());
        }

        $link = (string) $response->json('data.link', '');

        return [
            'link' => $link,
            'tx_ref' => $payload['tx_ref'],
            'status' => 'pending',
            'gateway' => $this->getName(),
        ];
    }

    /**
     * Verify a transaction by its tx_ref.
     *
     * {@inheritDoc}
     */
    public function verify(string $externalReference): array
    {
        $response = $this->client()
            ->get('/transactions/verify_by_reference', ['tx_ref' => $externalReference]);

        if ($response->failed() || ($response->json('status') !== 'success')) {
            Log::warning('Flutterwave verify failed', [
                'tx_ref' => $externalReference,
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
        $status = (string) ($data['status'] ?? 'failed');

        return [
            'status' => $status === 'successful' ? 'success' : $status,
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? ''),
            'payment_method' => $this->resolvePaymentMethod($data),
            'paid_at' => $status === 'successful' ? (string) ($data['created_at'] ?? now()->toISOString()) : null,
            'raw' => $data,
        ];
    }

    /**
     * Validate the Flutterwave webhook using the verif-hash header.
     *
     * {@inheritDoc}
     */
    public function handleWebhook(array $payload, array $headers): array
    {
        $verifHash = (string) ($headers['verif-hash'] ?? $headers['HTTP_VERIF_HASH'] ?? '');

        if ($this->webhookSecret === '' || $verifHash === '' || !hash_equals($this->webhookSecret, $verifHash)) {
            Log::warning('Flutterwave webhook: invalid verif-hash', [
                'ip' => request()->ip(),
            ]);

            throw new InvalidWebhookSignatureException('Invalid webhook signature.');
        }

        $event = (string) ($payload['event'] ?? '');
        $data = (array) ($payload['data'] ?? []);

        if ($event !== 'charge.completed') {
            return [
                'event' => $event,
                'tx_ref' => '',
                'status' => 'ignored',
                'amount' => 0.0,
                'currency' => '',
                'payment_method' => null,
                'raw' => $data,
            ];
        }

        $status = (string) ($data['status'] ?? '');
        $txRef = (string) ($data['tx_ref'] ?? $data['txRef'] ?? '');

        return [
            'event' => $event,
            'tx_ref' => $txRef,
            'flw_ref' => (string) ($data['flw_ref'] ?? ''),
            'status' => $status === 'successful' ? 'success' : $status,
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? ''),
            'payment_method' => $this->resolvePaymentMethod($data),
            'raw' => $data,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'flutterwave';
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Map Flutterwave payment type to our internal method label.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePaymentMethod(array $data): ?string
    {
        $type = strtolower((string) ($data['payment_type'] ?? ''));

        return match (true) {
            str_contains($type, 'mtn') => 'mobile_money',
            str_contains($type, 'orange') => 'orange_money',
            str_contains($type, 'mobile') => 'mobile_money',
            str_contains($type, 'card') => 'card',
            $type !== '' => $type,
            default => null,
        };
    }
}
