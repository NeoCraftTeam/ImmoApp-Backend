<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\StripeSavedCardServiceInterface;
use App\Exceptions\PaymentGatewayException;
use App\Http\Requests\Api\V1\StripePaymentMethodRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * REST endpoints to list, delete, and set the default Stripe saved card
 * for the authenticated user, plus a SetupIntent helper for the profile
 * "Ajouter une carte" flow.
 *
 * All endpoints require `auth:sanctum`. The Stripe Customer is resolved
 * lazily through Cashier — users who never opted in to save a card
 * remain free of a Stripe Customer record and just see an empty list.
 *
 * @OA\Tag(name="💳 Cartes Stripe", description="Gestion des moyens de paiement Stripe sauvegardés")
 */
final class StripePaymentMethodController
{
    public function __construct(
        protected StripeSavedCardServiceInterface $stripe,
    ) {}

    /**
     * List the saved cards on the authenticated user's Stripe Customer.
     *
     * Returns an empty array when the user has no Stripe Customer yet —
     * this is the expected baseline state and not an error.
     *
     * @OA\Get(
     *     path="/api/v1/payments/stripe/payment-methods",
     *     summary="Lister les cartes Stripe enregistrées",
     *     tags={"💳 Cartes Stripe"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Liste des cartes"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->hasStripeId()) {
            return response()->json([
                'data' => [],
            ]);
        }

        try {
            $cards = $this->stripe->listSavedCards((string) $user->stripe_id);
        } catch (PaymentGatewayException $e) {
            Log::warning('Stripe list saved cards failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'data' => $cards,
        ]);
    }

    /**
     * Detach a saved card from the authenticated user's Stripe Customer.
     *
     * @OA\Delete(
     *     path="/api/v1/payments/stripe/payment-methods/{paymentMethod}",
     *     summary="Supprimer une carte enregistrée",
     *     tags={"💳 Cartes Stripe"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="paymentMethod", in="path", required=true, @OA\Schema(type="string", example="pm_xxx")),
     *
     *     @OA\Response(response=204, description="Carte supprimée"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=404, description="Aucune carte enregistrée"),
     *     @OA\Response(response=422, description="Identifiant invalide")
     * )
     */
    public function destroy(StripePaymentMethodRequest $request, string $paymentMethod): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->hasStripeId()) {
            return response()->json([
                'message' => 'Aucune carte enregistrée sur ce compte.',
            ], 404);
        }

        try {
            $this->stripe->detachSavedCard((string) $user->stripe_id, $paymentMethod);
        } catch (PaymentGatewayException $e) {
            Log::warning('Stripe detach saved card failed', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * Mark a saved card as the user's default for off-session charges
     * (and Cashier subscription invoicing).
     *
     * @OA\Post(
     *     path="/api/v1/payments/stripe/payment-methods/{paymentMethod}/set-default",
     *     summary="Définir une carte comme moyen par défaut",
     *     tags={"💳 Cartes Stripe"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="paymentMethod", in="path", required=true, @OA\Schema(type="string", example="pm_xxx")),
     *
     *     @OA\Response(response=200, description="Carte définie par défaut"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=404, description="Aucune carte enregistrée"),
     *     @OA\Response(response=422, description="Identifiant invalide")
     * )
     */
    public function setDefault(StripePaymentMethodRequest $request, string $paymentMethod): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->hasStripeId()) {
            return response()->json([
                'message' => 'Aucune carte enregistrée sur ce compte.',
            ], 404);
        }

        try {
            $this->stripe->setDefaultSavedCard((string) $user->stripe_id, $paymentMethod);
        } catch (PaymentGatewayException $e) {
            Log::warning('Stripe set default saved card failed', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethod,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Carte définie comme moyen de paiement par défaut.',
        ]);
    }

    /**
     * Return a SetupIntent client secret so the frontend can collect a
     * new card (no charge) and attach it to the Customer. Used by the
     * « Ajouter une carte » flow in the profile screen.
     *
     * @OA\Post(
     *     path="/api/v1/payments/stripe/setup-intent",
     *     summary="Obtenir un SetupIntent Stripe",
     *     tags={"💳 Cartes Stripe"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Client secret retourné"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=502, description="Erreur Stripe")
     * )
     */
    public function setupIntent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Avoid the unnecessary Stripe API round-trip when the user
        // already has a Customer ; only `createAs…` when missing.
        $customerId = $user->hasStripeId()
            ? (string) $user->stripeId()
            : (string) $user->createAsStripeCustomer()->id;

        try {
            $intent = $this->stripe->createSetupIntent($customerId);
        } catch (PaymentGatewayException $e) {
            Log::warning('Stripe createSetupIntent failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'data' => [
                'client_secret' => $intent['client_secret'],
                'id' => $intent['id'],
            ],
        ]);
    }
}
