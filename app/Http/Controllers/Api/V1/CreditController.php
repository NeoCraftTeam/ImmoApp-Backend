<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Http\Resources\PointPackageResource;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Services\FedaPayService;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="🎯 Crédits / Points", description="Gestion des packs de points et achats")
 */
final class CreditController
{
    public function __construct(
        protected FedaPayService $fedaPay,
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
     * Initiate a FedaPay purchase for a point package.
     *
     * @OA\Post(
     *     path="/api/v1/credits/purchase/{package}",
     *     summary="Acheter un pack de crédits",
     *     description="Initialise un paiement FedaPay pour acheter le pack de crédits sélectionné. Retourne une URL de paiement.",
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
     *     @OA\Response(response=500, description="Erreur FedaPay")
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

        $callbackUrl = $request->input(
            'callback_url',
            config('app.frontend_url', config('app.url')).'/credits/callback'
        );

        $paymentData = $this->fedaPay->createCreditPayment(
            $package->price,
            $user,
            $package->id,
            $callbackUrl,
        );

        if (!$paymentData['success']) {
            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement.',
                'error' => config('app.debug') ? ($paymentData['message'] ?? null) : null,
            ], 500);
        }

        try {
            Payment::create([
                'user_id' => $user->id,
                'amount' => $package->price,
                'transaction_id' => (string) $paymentData['transaction_id'],
                'status' => PaymentStatus::PENDING,
                'payment_method' => PaymentMethod::FEDAPAY,
                'type' => PaymentType::CREDIT,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur création paiement crédits: '.$e->getMessage());

            return response()->json([
                'message' => 'Erreur technique lors de l\'initialisation.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'payment_url' => $paymentData['url'],
            'message' => 'Redirigez l\'utilisateur vers cette URL pour payer.',
        ]);
    }

    /**
     * Verify and optionally force-process a credit purchase.
     *
     * If the webhook hasn't arrived yet, checks with FedaPay and credits the points.
     *
     * @OA\Post(
     *     path="/api/v1/credits/verify-purchase",
     *     summary="Vérifier un achat de crédits",
     *     description="Vérifie le dernier achat de crédits de l'utilisateur auprès de FedaPay. Si le paiement est approuvé, crédite les points.",
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

        // Payment is still PENDING — check with FedaPay
        $result = $this->fedaPay->retrieveTransaction((int) $payment->transaction_id);

        if ($result['success'] && $result['status'] === 'approved') {
            $payment->update(['status' => PaymentStatus::SUCCESS]);

            // Credit points if not already done (idempotency via unique transaction check)
            $alreadyCredited = $user->pointTransactions()
                ->where('payment_id', $payment->id)
                ->exists();

            if (!$alreadyCredited) {
                $metadata = [];
                try {
                    $transaction = \FedaPay\Transaction::retrieve((int) $payment->transaction_id);
                    /** @var array<string, mixed> $metadata */
                    $metadata = (array) ($transaction->metadata ?? []); // @phpstan-ignore class.notFound
                } catch (\Exception) {
                    // fall back to amount-based lookup
                }

                $packageId = $metadata['package_id'] ?? null;
                $package = $packageId ? PointPackage::find($packageId) : null;

                if (!$package) {
                    $package = PointPackage::where('price', $payment->amount)
                        ->where('is_active', true)
                        ->first();
                }

                if ($package) {
                    $this->pointService->credit(
                        $user,
                        $package->points_awarded,
                        PointTransactionType::PURCHASE,
                        "Achat pack: {$package->name}",
                        $payment->id,
                    );
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => 'Achat de crédits confirmé.',
                'point_balance' => (int) $user->fresh()->point_balance,
            ]);
        }

        if ($result['success'] && in_array($result['status'], ['canceled', 'declined', 'refunded'])) {
            $payment->update(['status' => PaymentStatus::FAILED]);

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
}
