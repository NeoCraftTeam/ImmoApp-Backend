<?php

declare(strict_types=1);

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment and return data needed by the frontend.
     *
     * @param  array{
     *     amount: float,
     *     currency: string,
     *     email: string,
     *     phone?: string,
     *     name: string,
     *     tx_ref: string,
     *     redirect_url: string,
     *     payment_method?: string,
     *     payment_options?: string,
     *     description?: string,
     *     meta?: array<string, mixed>
     * } $payload
     * @return array{link: string, tx_ref: string, status: string, gateway: string, stripe_flow?: string, raw?: array<string, mixed>}
     */
    public function initiate(array $payload): array;

    /**
     * Verify a transaction by its external reference (tx_ref or charge ID).
     *
     * @return array{status: string, amount: float, currency: string, payment_method: string|null, paid_at: string|null, raw: array<string, mixed>}
     */
    public function verify(string $externalReference): array;

    /**
     * Validate and parse an incoming webhook payload.
     * Must verify the signature and return normalised data.
     *
     * `event_id` is the gateway-provided unique event identifier and
     * MUST be returned when available so the orchestrator can dedupe
     * provider retries. When the gateway doesn't expose an explicit
     * id (e.g. mobile-money providers that re-POST on retry without a
     * stable id), the implementation should derive one from a stable
     * payload+timestamp tuple. Returning `null` opts out of dedup —
     * the orchestrator's row-lock + terminal-state guards still apply.
     *
     * `$rawBody` is the exact bytes of the incoming request body. HMAC
     * signatures MUST be verified against these bytes — re-encoding the
     * parsed `$payload` can produce a different string (slash/unicode
     * escaping, key order, number formatting) and break verification.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{event: string, event_id: string|null, tx_ref: string, status: string, amount: float, currency: string, payment_method: string|null, raw: array<string, mixed>}
     */
    public function handleWebhook(array $payload, array $headers, ?string $rawBody = null): array;

    /**
     * Return the unique gateway identifier (e.g. 'geniuspay', 'stripe').
     */
    public function getName(): string;

    /**
     * Initiate a refund for a completed transaction.
     *
     * @return array{refund_id: string, status: string, amount_refunded: float, raw: array<string, mixed>}
     */
    public function refund(string $gatewayTransactionId, ?float $amount = null): array;
}
