<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\HandlePostPaymentActions;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Requests\Api\V1\FlutterwaveInitiateRequest;
use App\Http\Requests\Api\V1\FlutterwaveVerifyRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Paiements", description="Gestion des paiements (multi-gateway)")
 */
final class PaymentController
{
    public function __construct(
        protected HandlePostPaymentActions $postPaymentActions,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Initiate a Flutterwave payment.
     *
     * Intended for: subscription, credit purchases.
     * Returns a hosted checkout link to redirect the user.
     *
     * @OA\Post(
     *     path="/api/v1/payments/flutterwave/initiate",
     *     summary="Initier un paiement Flutterwave",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="amount", type="number", example=150000),
     *             @OA\Property(property="type", type="string", example="credit"),
     *             @OA\Property(property="payment_method", type="string", example="mobile_money"),
     *             @OA\Property(property="phone_number", type="string", example="+237699000000")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Lien de paiement retourné"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
    public function initiate(FlutterwaveInitiateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $type = $validated['type'];
        $amount = $this->paymentService->resolveAmountForType($type, $validated);

        if ($amount === null) {
            return response()->json([
                'message' => 'Impossible de déterminer le montant pour ce type de paiement.',
            ], 422);
        }

        // Wrap promo code validation + payment creation in a single transaction
        // to prevent race conditions on single-use promo codes.
        return DB::transaction(function () use ($validated, $user, $type, $amount): JsonResponse {
            $appliedPromoCode = null;
            $finalAmount = $amount;

            if (!empty($validated['promo_code'])) {
                $promoCode = PromoCode::where('code', strtoupper((string) $validated['promo_code']))
                    ->lockForUpdate()
                    ->first();

                if ($promoCode && $promoCode->isValidForUser($user, $type)) {
                    $finalAmount = max(0.0, $finalAmount - $promoCode->calculateDiscount($finalAmount));
                    $appliedPromoCode = $promoCode;
                }
            }

            $description = match ($type) {
                'subscription' => 'Abonnement agence',
                'credit' => 'Achat de crédits',
                default => 'Paiement KeyHome',
            };

            $result = $this->paymentService->createPayment($user, [
                'amount' => $finalAmount,
                'type' => $type,
                'payment_method' => $validated['payment_method'] ?? 'flutterwave',
                'phone_number' => $validated['phone_number'] ?? null,

                'agency_id' => $validated['agency_id'] ?? null,
                'plan_id' => $validated['plan_id'] ?? null,
                'period' => $validated['period'] ?? null,
                'description' => $description,
                'meta' => [
                    'package_id' => ($type === 'credit') ? ($validated['plan_id'] ?? null) : null,
                ],
            ]);

            if ($appliedPromoCode !== null) {
                PromoCodeUsage::create([
                    'promo_code_id' => $appliedPromoCode->id,
                    'user_id' => $user->id,
                    'payment_id' => $result['payment']->id,
                ]);
                $appliedPromoCode->increment('used_count');
            }

            return response()->json([
                'reference' => $result['payment']->id,
                'payment_link' => $result['link'],
                'tx_ref' => $result['tx_ref'],
                'gateway' => $result['gateway'],
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Verify a Flutterwave payment after the user returns from checkout.
     *
     * @OA\Post(
     *     path="/api/v1/payments/flutterwave/verify",
     *     summary="Vérifier un paiement Flutterwave",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="tx_ref", type="string", example="KH-ABCDEF123456")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Statut du paiement"),
     *     @OA\Response(response=404, description="Paiement introuvable")
     * )
     */
    public function verify(FlutterwaveVerifyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $payment = Payment::where('transaction_id', $validated['tx_ref'])
            ->where('user_id', $user->id)
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $payment = $this->paymentService->syncPaymentStatus($payment);

        if ($payment->isPaid()) {
            $this->postPaymentActions->execute($payment, (array) ($payment->gateway_response ?? []));
        }

        return response()->json([
            'status' => $payment->status->value,
            'is_paid' => $payment->isPaid(),
            'reference' => $payment->id,
            'ad_id' => $payment->ad_id,
            'tx_ref' => $payment->transaction_id,
            'gateway' => $payment->gateway?->value,
            'payment_method' => $payment->payment_method?->value,
            'payment_method_label' => $payment->payment_method?->label(),
        ]);
    }

    /**
     * Cancel a pending Flutterwave payment on user request.
     *
     * @OA\Post(
     *     path="/api/v1/payments/flutterwave/cancel",
     *     summary="Annuler un paiement Flutterwave en attente",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="tx_ref", type="string", example="KH-ABCDEF123456")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Paiement annulé"),
     *     @OA\Response(response=404, description="Paiement introuvable"),
     *     @OA\Response(response=409, description="Paiement déjà traité")
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'tx_ref' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($user, $request): JsonResponse {
            $payment = Payment::where('transaction_id', $request->input('tx_ref'))
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return response()->json(['message' => 'Paiement introuvable.'], 404);
            }

            if ($payment->isTerminal()) {
                return response()->json([
                    'message' => 'Ce paiement a déjà été traité.',
                    'status' => $payment->status->value,
                ], 409);
            }

            $payment->forceFill(['status' => PaymentStatus::CANCELLED])->save();

            Log::info('Payment cancelled by user', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Paiement annulé avec succès.',
                'status' => 'cancelled',
            ]);
        });
    }

    /**
     * Handle a webhook from any supported payment gateway.
     * The {gateway} route parameter is validated by the route constraint.
     *
     * @OA\Post(
     *     path="/api/v1/webhooks/{gateway}",
     *     summary="Webhook passerelle de paiement",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Parameter(name="gateway", in="path", required=true, @OA\Schema(type="string", enum={"flutterwave","fedapay"})),
     *
     *     @OA\Response(response=200, description="Webhook traité"),
     *     @OA\Response(response=401, description="Signature invalide")
     * )
     */
    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        Log::info("--- WEBHOOK {$gateway} START ---");

        $payload = $request->all();
        $headers = [
            'verif-hash' => (string) $request->header('verif-hash', ''),
            'HTTP_VERIF_HASH' => (string) $request->header('verif-hash', ''),
            'flutterwave-signature' => (string) $request->header('flutterwave-signature', ''),
            'x-fedapay-signature' => (string) $request->header('x-fedapay-signature', ''),
        ];

        try {
            DB::transaction(function () use ($payload, $headers, $gateway): void {
                $data = $this->paymentService->processWebhook($payload, $headers, $gateway);
                $txRef = (string) ($data['tx_ref'] ?? '');

                if ($txRef === '') {
                    return;
                }

                $payment = Payment::where('transaction_id', $txRef)
                    ->where('gateway', $gateway)
                    ->first();

                if (!$payment || !$payment->isPaid()) {
                    return;
                }

                $this->postPaymentActions->execute($payment, (array) ($data['raw'] ?? []));
            });
        } catch (InvalidWebhookSignatureException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        } catch (PaymentGatewayException|\Exception $e) {
            Log::error("{$gateway} webhook error: ".$e->getMessage());

            return response()->json(['status' => 'error'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Return the authenticated user's payment history.
     *
     * @OA\Get(
     *     path="/api/v1/payments/history",
     *     summary="Historique des paiements",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Liste paginée des transactions")
     * )
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->with('pointPackage')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
