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
     *     phone: string,
     *     name: string,
     *     tx_ref: string,
     *     redirect_url: string,
     *     payment_options?: string,
     *     description?: string,
     *     meta?: array<string, mixed>
     * } $payload
     * @return array{link: string, tx_ref: string, status: string, gateway: string}
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
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{event: string, tx_ref: string, status: string, amount: float, currency: string, payment_method: string|null, raw: array<string, mixed>}
     */
    public function handleWebhook(array $payload, array $headers): array;

    /**
     * Return the unique gateway identifier (e.g. 'flutterwave').
     */
    public function getName(): string;
}
