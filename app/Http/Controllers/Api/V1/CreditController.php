<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Exceptions\PaymentGatewayException;
use App\Http\Resources\PointPackageResource;
use App\Mail\CreditPurchaseConfirmationMail;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\UnlockedAd;
use App\Services\Payment\PaymentService;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🎯 Crédits / Points", description="Gestion des packs de points et achats")
 */
final class CreditController
{
    public function __construct(
        protected PaymentService $paymentService,
        protected PointService $pointService,
    ) {}

    /**
     * List all active point packages.
     *
     * @OA\Get(
     *     path="/api/v1/credits/packages",
     *     summary="Lister les packs de crédits disponibles",
     *     description="Retourne tous les packs de points actifs avec leurs prix et avantages.",
     *     tags={"🎯 Crédits / Points"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste des packs",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/PointPackageResource"))
     *     )
     * )
     */
    public function packages(): AnonymousResourceCollection
    {
        $packages = PointPackage::active()->get();

        return PointPackageResource::collection($packages);
    }

    /**
     * Return the authenticated user's current point balance.
     *
     * @OA\Get(
     *     path="/api/v1/credits/balance",
     *     summary="Obtenir le solde de crédits",
     *     description="Retourne le solde de points/crédits de l'utilisateur authentifié.",
     *     tags={"🎯 Crédits / Points"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Solde actuel",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="point_balance", type="integer", example=25)
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'point_balance' => (int) $user->point_balance,
        ]);
    }

    /**
     * Initiate a purchase for a point package.
     *
     * @OA\Post(
     *     path="/api/v1/credits/purchase/{package}",
     *     summary="Acheter un pack de crédits",
     *     description="Initialise un paiement pour acheter le pack de crédits sélectionné. Retourne une URL de paiement.",
     *     tags={"🎯 Crédits / Points"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="package", in="path", required=true, description="UUID du pack", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="callback_url", type="string", description="URL de retour après paiement")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Lien de paiement généré",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="payment_url", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=422, description="Pack non disponible"),
     *     @OA\Response(response=500, description="Erreur paiement")
     * )
     */
    public function purchase(Request $request, PointPackage $package): JsonResponse
    {
        if (!$package->is_active) {
            return response()->json([
                'message' => 'Ce pack n\'est plus disponible.',
            ], 422);
        }

        $user = $request->user();

        try {
            $result = $this->paymentService->createPayment($user, [
                'amount' => (float) $package->price,
                'type' => PaymentType::CREDIT->value,
                'payment_method' => 'flutterwave',
                'plan_id' => $package->id,
                'description' => "Achat pack: {$package->name}",
                'meta' => [
                    'package_id' => $package->id,
                ],
            ]);
        } catch (PaymentGatewayException $e) {
            Log::error('Erreur initiation paiement crédits: '.$e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'payment_url' => $result['link'],
            'message' => 'Redirigez l\'utilisateur vers cette URL pour payer.',
        ]);
    }

    /**
     * Verify and optionally force-process a credit purchase.
     *
     * If the webhook hasn't arrived yet, checks with the payment gateway and credits the points.
     *
     * @OA\Post(
     *     path="/api/v1/credits/verify-purchase",
     *     summary="Vérifier un achat de crédits",
     *     description="Vérifie le dernier achat de crédits de l'utilisateur. Si le paiement est approuvé, crédite les points.",
     *     tags={"🎯 Crédits / Points"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Statut de l'achat",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", enum={"completed", "pending", "failed", "not_found"}),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="point_balance", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=404, description="Aucun achat trouvé"),
     *     @OA\Response(response=422, description="Paiement échoué")
     * )
     */
    public function verifyPurchase(Request $request): JsonResponse
    {
        $user = $request->user();

        $payment = Payment::where('user_id', $user->id)
            ->where('type', PaymentType::CREDIT)
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Aucun achat de crédits trouvé.',
                'point_balance' => (int) $user->point_balance,
            ], 404);
        }

        if ($payment->status === PaymentStatus::SUCCESS) {
            return response()->json([
                'status' => 'completed',
                'message' => 'Achat de crédits confirmé.',
                'point_balance' => (int) $user->fresh()->point_balance,
            ]);
        }

        if ($payment->status === PaymentStatus::FAILED) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Le paiement a échoué.',
                'point_balance' => (int) $user->point_balance,
            ], 422);
        }

        // Payment is still PENDING — verify via Flutterwave gateway
        $synced = $this->paymentService->syncPaymentStatus($payment);

        if ($synced->status === PaymentStatus::SUCCESS) {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $synced): JsonResponse {
                /** @var Payment $lockedPayment */
                $lockedPayment = Payment::where('id', $synced->id)
                    ->lockForUpdate()
                    ->first();

                // Idempotency check — safe inside the lock
                $alreadyCredited = $user->pointTransactions()
                    ->where('payment_id', $lockedPayment->id)
                    ->exists();

                if (!$alreadyCredited) {
                    $raw = (array) ($lockedPayment->gateway_response ?? []);
                    $meta = (array) ($raw['meta'] ?? []);
                    $packageId = $meta['package_id'] ?? null;
                    $package = $packageId ? PointPackage::find($packageId) : null;

                    if (!$package) {
                        $package = PointPackage::where('price', $lockedPayment->amount)
                            ->where('is_active', true)
                            ->first();
                    }

                    if ($package) {
                        $this->pointService->credit(
                            $user,
                            $package->points_awarded,
                            PointTransactionType::PURCHASE,
                            "Achat pack: {$package->name}",
                            $lockedPayment->id,
                        );

                        try {
                            Mail::to($user->email)->send(new CreditPurchaseConfirmationMail(
                                $user,
                                $package,
                                $lockedPayment,
                                (int) $user->fresh()->point_balance,
                            ));
                        } catch (\Exception $e) {
                            Log::error('Erreur email achat crédits: '.$e->getMessage());
                        }
                    }
                }

                return response()->json([
                    'status' => 'completed',
                    'message' => 'Achat de crédits confirmé.',
                    'point_balance' => (int) $user->fresh()->point_balance,
                ]);
            });
        }

        if ($synced->status === PaymentStatus::FAILED) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Le paiement n\'a pas abouti.',
                'point_balance' => (int) $user->point_balance,
            ], 422);
        }

        return response()->json([
            'status' => 'pending',
            'message' => 'Le paiement est en cours de confirmation.',
            'point_balance' => (int) $user->point_balance,
        ]);
    }

    /**
     * Unlock an ad using the user's credit balance.
     *
     * @OA\Post(
     *     path="/api/v1/payments/initialize/{ad}",
     *     summary="Débloquer une annonce avec des crédits",
     *     description="Vérifie le solde de crédits et débloque l'annonce si suffisant.",
     *     tags={"🎯 Crédits / Points"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, description="UUID de l'annonce", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Annonce débloquée ou déjà accessible"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=402, description="Crédits insuffisants")
     * )
     */
    public function unlock(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        if ($ad->user_id === $user->id) {
            return response()->json(['status' => 'owner']);
        }

        if (UnlockedAd::where('user_id', $user->id)->where('ad_id', $ad->id)->exists()) {
            return response()->json(['status' => 'already_unlocked']);
        }

        $cost = (int) \App\Models\Setting::get('unlock_cost_points', 2);

        if ($user->point_balance < $cost) {
            return response()->json([
                'status' => 'insufficient_points',
                'current_balance' => (int) $user->point_balance,
                'required_points' => $cost,
                'packages' => PointPackageResource::collection(PointPackage::active()->get()),
            ], 402);
        }

        DB::transaction(function () use ($user, $ad, $cost): void {
            $this->pointService->deduct(
                $user,
                $cost,
                "Déblocage annonce: {$ad->title}",
                $ad->id,
            );

            UnlockedAd::firstOrCreate(
                ['user_id' => $user->id, 'ad_id' => $ad->id],
                ['unlocked_at' => now()],
            );
        });

        return response()->json([
            'status' => 'unlocked',
            'points_balance' => (int) $user->fresh()->point_balance,
        ]);
    }
}
