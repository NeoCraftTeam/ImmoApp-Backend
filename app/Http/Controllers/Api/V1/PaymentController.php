<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Http\Requests\Api\V1\FlutterwaveInitiateRequest;
use App\Http\Requests\Api\V1\FlutterwaveVerifyRequest;
use App\Http\Resources\PaymentResource;
use App\Mail\CreditPurchaseConfirmationMail;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Paiements", description="Gestion des paiements Flutterwave")
 */
final class PaymentController
{
    public function __construct(
        protected PointService $pointService,
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
    public function flutterwaveInitiate(FlutterwaveInitiateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $type = $validated['type'];
        $amount = $this->resolveAmountForType($type, $validated);

        if ($amount === null) {
            return response()->json([
                'message' => 'Impossible de déterminer le montant pour ce type de paiement.',
            ], 422);
        }

        $description = match ($type) {
            'subscription' => 'Abonnement agence',
            'credit' => 'Achat de crédits',
            default => 'Paiement KeyHome',
        };

        $result = $this->paymentService->createPayment($user, [
            'amount' => $amount,
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

        return response()->json([
            'reference' => $result['payment']->id,
            'payment_link' => $result['link'],
            'tx_ref' => $result['tx_ref'],
            'gateway' => $result['gateway'],
            'status' => 'pending',
        ]);
    }

    /**
     * Resolve the authoritative price for a payment type from server data.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveAmountForType(string $type, array $validated): ?float
    {
        return match ($type) {
            'credit' => $this->resolveCreditAmount($validated['plan_id'] ?? null),
            'subscription' => $this->resolveSubscriptionAmount($validated['plan_id'] ?? null, $validated['period'] ?? 'monthly'),
            default => null,
        };
    }

    private function resolveCreditAmount(?string $packageId): ?float
    {
        if (!$packageId) {
            return null;
        }

        $package = PointPackage::where('id', $packageId)->where('is_active', true)->first();

        return $package ? (float) $package->price : null;
    }

    private function resolveSubscriptionAmount(?string $planId, string $period): ?float
    {
        if (!$planId) {
            return null;
        }

        $plan = \App\Models\SubscriptionPlan::where('id', $planId)->where('is_active', true)->first();

        if (!$plan) {
            return null;
        }

        return $period === 'yearly' && $plan->price_yearly
            ? (float) $plan->price_yearly
            : (float) $plan->price;
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
    public function flutterwaveVerify(FlutterwaveVerifyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $payment = Payment::where('transaction_id', $validated['tx_ref'])
            ->where('user_id', $user->id)
            ->where('gateway', 'flutterwave')
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $payment = $this->paymentService->syncPaymentStatus($payment);

        if ($payment->isPaid()) {
            $this->handlePostPaymentActions($payment, (array) ($payment->gateway_response ?? []));
        }

        return response()->json([
            'status' => $payment->status->value,
            'is_paid' => $payment->isPaid(),
            'reference' => $payment->id,
            'ad_id' => $payment->ad_id,
            'tx_ref' => $payment->transaction_id,
            'gateway' => $payment->gateway?->value,
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
    public function flutterwaveCancel(Request $request): JsonResponse
    {
        $request->validate([
            'tx_ref' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($user, $request): JsonResponse {
            $payment = Payment::where('transaction_id', $request->input('tx_ref'))
                ->where('user_id', $user->id)
                ->where('gateway', 'flutterwave')
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
     * Handle Flutterwave webhook (charge.completed event).
     *
     * Validates the verif-hash header and processes the payment update.
     *
     * @OA\Post(
     *     path="/api/v1/webhooks/flutterwave",
     *     summary="Webhook Flutterwave",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Response(response=200, description="Webhook traité"),
     *     @OA\Response(response=401, description="Signature invalide")
     * )
     */
    public function flutterwaveWebhook(Request $request): JsonResponse
    {
        Log::info('--- WEBHOOK FLUTTERWAVE START ---');

        $payload = $request->all();
        $headers = [
            'verif-hash' => (string) $request->header('verif-hash', ''),
            'HTTP_VERIF_HASH' => (string) $request->header('verif-hash', ''),
            'flutterwave-signature' => (string) $request->header('flutterwave-signature', ''),
        ];

        try {
            DB::transaction(function () use ($payload, $headers): void {
                $this->paymentService->processWebhook($payload, $headers, 'flutterwave');

                // Post-processing: handle subscriptions, credit points
                $txRef = (string) ($payload['data']['tx_ref'] ?? '');
                $status = (string) ($payload['data']['status'] ?? '');

                if ($txRef === '' || $status !== 'successful') {
                    return;
                }

                $payment = Payment::where('transaction_id', $txRef)
                    ->where('gateway', 'flutterwave')
                    ->first();

                if (!$payment || !$payment->isPaid()) {
                    return;
                }

                $this->handlePostPaymentActions($payment, $payload['data'] ?? []);
            });
        } catch (\App\Exceptions\InvalidWebhookSignatureException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        } catch (\App\Exceptions\PaymentGatewayException|\Exception $e) {
            Log::error('Flutterwave webhook error: '.$e->getMessage());

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

    /**
     * Execute post-payment business actions (activate subscriptions, credit points).
     *
     * @param  array<string, mixed>  $webhookData
     */
    private function handlePostPaymentActions(Payment $payment, array $webhookData): void
    {
        DB::transaction(function () use ($payment, $webhookData): void {
            $this->executePostPaymentActions($payment, $webhookData);
        });
    }

    /**
     * @param  array<string, mixed>  $webhookData
     */
    private function executePostPaymentActions(Payment $payment, array $webhookData): void
    {
        $metadata = (array) ($webhookData['meta'] ?? []);

        if ($payment->type === PaymentType::SUBSCRIPTION) {
            $agencyId = $payment->agency_id ?? ($metadata['agency_id'] ?? null);
            $planId = $payment->plan_id ?? ($metadata['plan_id'] ?? null);
            $period = $payment->period ?? ($metadata['period'] ?? 'monthly');

            if ($agencyId && $planId) {
                $agency = \App\Models\Agency::find($agencyId);
                $plan = \App\Models\SubscriptionPlan::find($planId);
                if ($agency && $plan) {
                    $subscriptionService = new \App\Services\SubscriptionService;
                    $subscription = $subscriptionService->createSubscription($agency, $plan, $period, $payment);
                    $subscriptionService->activateSubscription($subscription);
                    Log::info("Abonnement activé (flutterwave): agence {$agency->id} - plan {$plan->id}");
                }
            }
        }

        if ($payment->type === PaymentType::CREDIT) {
            $packageId = $payment->plan_id ?? ($metadata['package_id'] ?? null);
            $package = $packageId ? \App\Models\PointPackage::find($packageId) : null;

            if (!$package) {
                $package = \App\Models\PointPackage::where('price', $payment->amount)
                    ->where('is_active', true)
                    ->first();
            }
            $buyer = User::find($payment->user_id);

            if ($package && $buyer) {
                $alreadyCredited = $buyer->pointTransactions()
                    ->where('payment_id', $payment->id)
                    ->exists();

                if (!$alreadyCredited) {
                    $this->pointService->credit(
                        $buyer,
                        $package->points_awarded,
                        PointTransactionType::PURCHASE,
                        "Achat pack: {$package->name}",
                        $payment->id
                    );
                    Log::info("Points crédités (flutterwave): {$package->points_awarded} → user {$buyer->id}");

                    try {
                        Mail::to($buyer->email)->send(new CreditPurchaseConfirmationMail(
                            $buyer,
                            $package,
                            $payment,
                            (int) $buyer->fresh()->point_balance,
                        ));
                    } catch (\Exception $e) {
                        Log::error('Erreur email achat crédits: '.$e->getMessage());
                    }
                }
            }
        }
    }
}
