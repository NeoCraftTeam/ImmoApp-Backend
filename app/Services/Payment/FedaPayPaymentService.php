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
 * FedaPay payment gateway implementation.
 *
 * API docs: https://docs.fedapay.com
 *
 * Flow:
 *  1. POST /v1/transactions        → creates a transaction + payment token
 *  2. POST /v1/transactions/{id}/token → get the checkout URL
 *  3. GET  /v1/transactions/{id}   → verify status
 *  4. POST to our /webhooks/fedapay — verified via X-FEDAPAY-SIGNATURE header
 */
final readonly class FedaPayPaymentService implements PaymentGatewayInterface
{
    private string $secretKey;

    private string $baseUrl;

    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = (string) config('payment.gateways.fedapay.secret_key', '');
        $this->baseUrl = rtrim((string) config('payment.gateways.fedapay.base_url', 'https://api.fedapay.com'), '/');
        $this->webhookSecret = (string) config('payment.gateways.fedapay.webhook_secret', '');
    }

    /** {@inheritDoc} */
    public function initiate(array $payload): array
    {
        $transactionResponse = $this->client()->post('/v1/transactions', [
            'description' => $payload['description'] ?? 'Paiement KeyHome',
            'amount' => (int) $payload['amount'],
            'currency' => ['iso' => $payload['currency']],
            'callback_url' => $payload['redirect_url'],
            'customer' => [
                'firstname' => (static function (string $fullName): string {
                    $trimmed = trim($fullName);
                    if ($trimmed === '') {
                        return 'Client';
                    }
                    $parts = explode(' ', $trimmed, 2);

                    return $parts[0];
                })($payload['name']),
                'lastname' => implode(' ', array_slice(explode(' ', $payload['name']), 1)) ?: '',
                'email' => $payload['email'],
                'phone_number' => [
                    'number' => ltrim(str_replace([' ', '-'], '', $payload['phone']), '+'),
                    'country' => 'CM',
                ],
            ],
            'custom_metadata' => [
                'tx_ref' => $payload['tx_ref'],
                ...(array) ($payload['meta'] ?? []),
            ],
        ]);

        if ($transactionResponse->failed()) {
            Log::error('FedaPay initiate failed (create transaction)', [
                'response' => $transactionResponse->json(),
                'status' => $transactionResponse->status(),
            ]);

            throw new PaymentGatewayException(
                'FedaPay: '.($transactionResponse->json('message') ?? 'Création de la transaction échouée.')
            );
        }

        $transactionId = $transactionResponse->json('v1/transaction.id');
        if (!$transactionId) {
            Log::error('FedaPay: missing transaction ID in response', ['response' => $transactionResponse->json()]);

            throw new PaymentGatewayException('FedaPay: ID de transaction manquant dans la réponse.');
        }

        // Generate the hosted payment token/link
        $tokenResponse = $this->client()->post("/v1/transactions/{$transactionId}/token");

        if ($tokenResponse->failed()) {
            Log::error('FedaPay initiate failed (create token)', [
                'transaction_id' => $transactionId,
                'response' => $tokenResponse->json(),
            ]);

            throw new PaymentGatewayException('FedaPay: Génération du lien de paiement échouée.');
        }

        $checkoutUrl = $tokenResponse->json('url')
            ?? ($this->baseUrl.'/checkout/'.$tokenResponse->json('token'));

        Log::info('FedaPay payment initiated', [
            'transaction_id' => $transactionId,
            'tx_ref' => $payload['tx_ref'],
            'amount' => $payload['amount'],
        ]);

        return [
            'link' => $checkoutUrl,
            'tx_ref' => $payload['tx_ref'],
            'status' => 'pending',
            'gateway' => $this->getName(),
            'gateway_transaction_id' => (string) $transactionId,
        ];
    }

    /** {@inheritDoc} */
    public function verify(string $externalReference): array
    {
        // externalReference is the FedaPay transaction ID stored in gateway_response
        $response = $this->client()->get("/v1/transactions/{$externalReference}");

        if ($response->failed()) {
            Log::error('FedaPay verify failed', [
                'reference' => $externalReference,
                'response' => $response->json(),
            ]);

            throw new PaymentGatewayException('FedaPay: Impossible de vérifier la transaction.');
        }

        $transaction = $response->json('v1/transaction') ?? $response->json();
        $status = $this->normaliseStatus((string) ($transaction['status'] ?? 'pending'));

        return [
            'status' => $status,
            'amount' => (float) ($transaction['amount'] ?? 0),
            'currency' => (string) ($transaction['currency']['iso'] ?? 'XOF'),
            'payment_method' => $transaction['payment_method'] ?? null,
            'paid_at' => $transaction['paid_at'] ?? null,
            'raw' => $transaction,
        ];
    }

    /** {@inheritDoc} */
    public function handleWebhook(array $payload, array $headers): array
    {
        $signature = $headers['X-FEDAPAY-SIGNATURE'] ?? $headers['x-fedapay-signature'] ?? '';

        if (!$this->validateWebhookSignature($payload, (string) $signature)) {
            Log::warning('FedaPay webhook: invalid signature', [
                'ip' => request()->ip(),
                'event' => $payload['name'] ?? 'unknown',
            ]);

            throw new InvalidWebhookSignatureException('FedaPay webhook signature invalide.');
        }

        $transaction = $payload['entity'] ?? [];
        $txRef = $transaction['custom_metadata']['tx_ref'] ?? '';

        return [
            'event' => $payload['name'] ?? 'unknown',
            'tx_ref' => $txRef,
            'status' => $this->normaliseStatus((string) ($transaction['status'] ?? '')),
            'amount' => (float) ($transaction['amount'] ?? 0),
            'currency' => (string) ($transaction['currency']['iso'] ?? 'XOF'),
            'payment_method' => $transaction['payment_method'] ?? null,
            'raw' => $payload,
        ];
    }

    /** {@inheritDoc} */
    public function getName(): string
    {
        return 'fedapay';
    }

    /** {@inheritDoc} */
    public function refund(string $gatewayTransactionId, ?float $amount = null): array
    {
        // FedaPay does not support programmatic refunds through public API v1.
        // Refunds must be initiated via the FedaPay dashboard.
        Log::warning('FedaPay refund attempted via API — not supported', [
            'transaction_id' => $gatewayTransactionId,
            'amount' => $amount,
        ]);

        throw new PaymentGatewayException('Les remboursements FedaPay doivent être effectués via le tableau de bord FedaPay.');
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->baseUrl($this->baseUrl)
            ->timeout(30);
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'approved', 'transferred' => 'success',
            'declined', 'failed', 'expired' => 'failed',
            'canceled' => 'cancelled',
            default => 'pending',
        };
    }

    private function validateWebhookSignature(array $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        $computed = hash_hmac('sha256', json_encode($payload) ?: '', $this->webhookSecret);

        return hash_equals($computed, $signature);
    }
}
