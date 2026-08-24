<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\StripeSavedCardServiceInterface;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\StripeCustomerMissingException;
use App\Support\StripeClientFactory;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Manages the saved-card surface of a Stripe Customer: listing stored cards,
 * detaching one, marking a default PaymentMethod, and creating a SetupIntent
 * so a new card can be stored without a charge (profile flow).
 *
 * Split out of StripePaymentService so the gateway (charge lifecycle) and the
 * saved-card management (Customer PaymentMethods) stay focused and separately
 * testable. Bound to {@see StripeSavedCardServiceInterface} in
 * AppServiceProvider; the shared StripeClient is built by
 * {@see StripeClientFactory}.
 */
final readonly class StripeSavedCardService implements StripeSavedCardServiceInterface
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = StripeClientFactory::make();
    }

    /**
     * List the PaymentMethods saved on a Stripe Customer (type=card only).
     *
     * @return array<int, array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, is_default: bool}>
     */
    public function listSavedCards(string $customerId): array
    {
        try {
            $list = $this->stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'card',
                'limit' => 20,
            ]);

            $customer = $this->stripe->customers->retrieve($customerId);
            $defaultPaymentMethod = (string) ($customer->invoice_settings->default_payment_method ?? '');
        } catch (ApiErrorException $e) {
            // Client Stripe inexistant (ex. stripe_id créé avec des clés de
            // test, ou Customer supprimé côté Stripe) : ce n'est PAS une
            // erreur pour l'utilisateur — il n'a simplement aucune carte.
            // On renvoie une liste vide au lieu d'un 5xx qui afficherait
            // « Impossible de récupérer vos cartes » dans le profil.
            if ($e->getStripeCode() === 'resource_missing') {
                Log::info('Stripe listSavedCards: customer inconnu, liste vide renvoyée', [
                    'customer_id' => $customerId,
                ]);

                return [];
            }

            Log::error('Stripe listSavedCards failed', [
                'customer_id' => $customerId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de récupérer vos moyens de paiement. Réessayez plus tard.',
                previous: $e,
            );
        }

        $cards = [];
        foreach ($list->data as $paymentMethod) {
            $card = $paymentMethod->card ?? null;
            if ($card === null) {
                continue;
            }
            $cards[] = [
                'id' => (string) $paymentMethod->id,
                'brand' => (string) ($card->brand ?? 'unknown'),
                'last4' => (string) ($card->last4 ?? '----'),
                'exp_month' => (int) ($card->exp_month ?? 0),
                'exp_year' => (int) ($card->exp_year ?? 0),
                'is_default' => $defaultPaymentMethod !== '' && (string) $paymentMethod->id === $defaultPaymentMethod,
            ];
        }

        return $cards;
    }

    /**
     * Detach a saved PaymentMethod from its Customer.
     *
     * After detachment Stripe returns the PaymentMethod object but the card
     * can no longer be charged off-session. Caller must ensure the
     * `$paymentMethodId` actually belongs to `$customerId` (defence in
     * depth — Stripe also enforces ownership server-side).
     */
    public function detachSavedCard(string $customerId, string $paymentMethodId): void
    {
        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ((string) ($paymentMethod->customer ?? '') !== $customerId) {
                throw new PaymentGatewayException('Cette carte n\'appartient pas à votre compte.');
            }

            $this->stripe->paymentMethods->detach($paymentMethodId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe detachSavedCard failed', [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de supprimer cette carte. Réessayez plus tard.',
                previous: $e,
            );
        }
    }

    /**
     * Mark a saved PaymentMethod as the Customer's default for future
     * invoices (Cashier subscriptions) AND for off-session charges driven
     * from KeyHome (we read `invoice_settings.default_payment_method` in
     * `listSavedCards` to surface the `is_default` flag).
     */
    public function setDefaultSavedCard(string $customerId, string $paymentMethodId): void
    {
        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ((string) ($paymentMethod->customer ?? '') !== $customerId) {
                throw new PaymentGatewayException('Cette carte n\'appartient pas à votre compte.');
            }

            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe setDefaultSavedCard failed', [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible de définir cette carte comme par défaut.',
                previous: $e,
            );
        }
    }

    /**
     * Create a SetupIntent so the frontend can save a new card WITHOUT a
     * charge (profile flow). Returns the SetupIntent client secret.
     *
     * @return array{client_secret: string, id: string}
     */
    public function createSetupIntent(string $customerId): array
    {
        try {
            $intent = $this->stripe->setupIntents->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
            ]);
        } catch (ApiErrorException $e) {
            // Customer inconnu (stripe_id périmé) : exception dédiée pour que
            // l'appelant puisse s'auto-réparer (nouveau Customer + retry).
            if ($e->getStripeCode() === 'resource_missing') {
                throw new StripeCustomerMissingException(
                    'Client Stripe introuvable : '.$customerId,
                    previous: $e,
                );
            }

            Log::error('Stripe createSetupIntent failed', [
                'customer_id' => $customerId,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayException(
                'Impossible d\'enregistrer une nouvelle carte. Réessayez plus tard.',
                previous: $e,
            );
        }

        return [
            'client_secret' => (string) $intent->client_secret,
            'id' => (string) $intent->id,
        ];
    }
}
