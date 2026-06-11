<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Stripe-specific surface for managing saved cards (PaymentMethods) on the
 * authenticated user's Customer record.
 *
 * Decoupled from {@see PaymentGatewayInterface} on purpose: only the
 * Stripe gateway exposes saved-card features (the mobile-money gateway doesn't have an
 * equivalent persistent vault). Pulling these methods into a dedicated
 * contract keeps {@see PaymentGatewayInterface} generic and lets us
 * mock the saved-card surface without touching the rest of the gateway.
 */
interface StripeSavedCardServiceInterface
{
    /**
     * List the PaymentMethods saved on a Stripe Customer (type=card only).
     *
     * @return array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>
     */
    public function listSavedCards(string $customerId): array;

    /**
     * Detach a saved PaymentMethod from its Customer.
     *
     * Implementations MUST verify ownership before detaching (defence in
     * depth — Stripe also enforces it server-side).
     */
    public function detachSavedCard(string $customerId, string $paymentMethodId): void;

    /**
     * Mark a saved PaymentMethod as the Customer's default for off-session
     * charges and Cashier invoicing.
     *
     * Implementations MUST verify ownership before updating.
     */
    public function setDefaultSavedCard(string $customerId, string $paymentMethodId): void;

    /**
     * Create a SetupIntent so the frontend can attach a new card to the
     * Customer without an associated charge.
     *
     * @return array{client_secret: string, id: string}
     */
    public function createSetupIntent(string $customerId): array;
}
