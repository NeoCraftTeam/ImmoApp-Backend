<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Mail\AdUnlockConfirmationMail;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\Setting;
use App\Models\User;
use App\Services\FedaPayService;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Paiements", description="Gestion des paiements FedaPay")
 */
final class PaymentController
{
    public function __construct(
        protected FedaPayService $fedaPay,
        protected PointService $pointService,
    ) {}

    /**
     * Return the current unlock pricing configuration (public).
     *
     * @OA\Get(
     *     path="/api/v1/payments/unlock-price",
     *     summary="Obtenir le prix de déblocage",
     *     description="Retourne le prix de déblocage en FCFA et le coût en crédits/points.",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Prix de déblocage",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="unlock_price", type="integer", example=500),
     *             @OA\Property(property="unlock_cost_points", type="integer", example=2)
     *         )
     *     )
     * )
     */
    public function getUnlockPrice(): JsonResponse
    {
        return response()->json([
            'unlock_price' => (int) Setting::get('unlock_price', 500),
            'unlock_cost_points' => (int) Setting::get('unlock_cost_points', 2),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/initialize/{ad}",
     *     summary="1. Initialiser une demande de paiement",
     *     description="Génère un lien de paiement sécurisé via FedaPay. Le frontend doit rediriger l'utilisateur vers 'payment_url'.",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="ad",
     *         in="path",
     *         required=true,
     *         description="UUID de l'annonce à débloquer",
     *
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Succès : Lien généré",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="payment_url", type="string", description="URL vers l'interface FedaPay"),
     *             @OA\Property(property="message", type="string", example="Redirigez l'utilisateur vers cette URL pour payer.")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=404, description="L'annonce demandée n'existe pas")
     * )
     */
    public function initialize(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        // Owner never needs to pay for their own ad
        if ($ad->user_id === $user->id) {
            return response()->json([
                'message' => 'Vous êtes le propriétaire de cette annonce.',
                'status' => 'owner',
            ]);
        }

        // Already unlocked check
        $alreadyUnlocked = \App\Models\UnlockedAd::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->exists();

        if ($alreadyUnlocked) {
            return response()->json([
                'message' => 'Annonce déjà débloquée.',
                'status' => 'already_unlocked',
            ]);
        }

        $cost = $this->pointService->unlockCost();

        // User has enough points — instant unlock
        if ($this->pointService->hasEnough($user, $cost)) {
            try {
                $this->pointService->deduct(
                    $user,
                    $cost,
                    "Déblocage annonce #{$ad->id}",
                    (string) $ad->id
                );

                \App\Models\UnlockedAd::firstOrCreate(
                    ['ad_id' => $ad->id, 'user_id' => $user->id],
                    ['unlocked_at' => now()]
                );

                return response()->json([
                    'status' => 'unlocked',
                    'message' => 'Annonce débloquée avec succès.',
                    'points_used' => $cost,
                    'points_balance' => $user->fresh()->point_balance,
                ]);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'status' => 'insufficient_points',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        // Not enough points — return available packages
        $packages = PointPackage::active()->get(['id', 'name', 'price', 'points_awarded', 'sort_order']);

        return response()->json([
            'status' => 'insufficient_points',
            'message' => 'Solde de points insuffisant. Achetez un pack pour continuer.',
            'required_points' => $cost,
            'current_balance' => $user->point_balance,
            'packages' => $packages,
        ], 402);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/webhook",
     *     summary="2. Webhook de validation (Usage interne FedaPay)",
     *     description="Cet endpoint est appelé automatiquement par FedaPay dès qu'une transaction change de statut. Ne pas appeler manuellement par le frontend.",
     *     tags={"💰 Paiements"},
     *
     *     @OA\RequestBody(
     *         description="Payload envoyé par FedaPay",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="event", type="string", example="transaction.approved"),
     *             @OA\Property(property="entity", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Webhook traité avec succès",
     *
     *         @OA\JsonContent(@OA\Property(property="status", type="string", example="ok"))
     *     )
     * )
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('--- WEBHOOK FEDAPAY START ---');

        $webhookSecret = (string) config('services.fedapay.webhook_secret', '');
        if ($webhookSecret === '') {
            Log::error('Webhook FedaPay rejeté: FEDAPAY_WEBHOOK_SECRET manquant.');

            return response()->json(['status' => 'error', 'message' => 'Webhook misconfigured'], 500);
        }

        if (!$this->hasValidWebhookSignature($request, $webhookSecret)) {
            Log::warning('Webhook FedaPay rejeté: signature invalide.');

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->only(['name', 'entity']);
        Log::info('FedaPay Webhook reçu:', ['event' => $event['name'] ?? 'unknown']);

        $transactionId = $event['entity']['id'] ?? null;
        if (!$transactionId) {
            return response()->json(['status' => 'error', 'message' => 'No transaction ID'], 400);
        }

        // P0-2 Fix: lockForUpdate + idempotency guard to prevent double processing
        return DB::transaction(function () use ($transactionId, $event) {
            $payment = Payment::where('transaction_id', (string) $transactionId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return response()->json(['status' => 'not_found'], 404);
            }

            // Idempotency guard: skip if already in a terminal state
            if (in_array($payment->status, [PaymentStatus::SUCCESS, PaymentStatus::FAILED], true)) {
                Log::info("Webhook ignoré: Paiement #{$payment->id} déjà traité (status: {$payment->status->value}).");

                return response()->json(['status' => 'already_processed'], 200);
            }

            // 1. Gestion du SUCCÈS
            if (isset($event['name']) && $event['name'] === 'transaction.approved') {
                $payment->forceFill(['status' => PaymentStatus::SUCCESS])->save();

                // Create UnlockedAd record for backoffice tracking (unlock payments only)
                if ($payment->type === PaymentType::UNLOCK && $payment->ad_id && $payment->user_id) {
                    \App\Models\UnlockedAd::firstOrCreate(
                        ['ad_id' => $payment->ad_id, 'user_id' => $payment->user_id],
                        ['payment_id' => $payment->id, 'unlocked_at' => now()]
                    );

                    // Send confirmation email to the buyer
                    try {
                        $ad = \App\Models\Ad::with('user')->find($payment->ad_id);
                        $buyer = User::find($payment->user_id);
                        if ($ad && $buyer) {
                            Mail::to($buyer->email)->send(new AdUnlockConfirmationMail($buyer, $ad, $payment));
                        }
                    } catch (\Exception $e) {
                        Log::error('Erreur envoi email déblocage annonce: '.$e->getMessage());
                    }
                }

                Log::info("Paiement #{$payment->id} validé.");

                // Logique spécifique aux abonnements
                $metadata = $event['entity']['metadata'] ?? [];
                if (isset($metadata['payment_type']) && $metadata['payment_type'] === 'subscription') {
                    $agencyId = $metadata['agency_id'] ?? null;
                    $planId = $metadata['plan_id'] ?? null;
                    $period = $metadata['period'] ?? 'monthly';

                    if ($agencyId && $planId) {
                        $agency = \App\Models\Agency::find($agencyId);
                        $plan = \App\Models\SubscriptionPlan::find($planId);

                        if ($agency && $plan) {
                            $subscriptionService = new \App\Services\SubscriptionService;
                            $subscription = $subscriptionService->createSubscription($agency, $plan, $period, $payment);
                            $subscriptionService->activateSubscription($subscription);
                            Log::info("Abonnement activé pour l'agence {$agency->id} - Plan {$plan->id} ({$period})");
                        }
                    }
                }
                // Point package credit handling
                if ($payment->type === PaymentType::CREDIT) {
                    $packageId = $metadata['package_id'] ?? null;
                    $package = PointPackage::find($packageId);
                    $buyer = User::find($payment->user_id);

                    if ($package && $buyer) {
                        $this->pointService->credit(
                            $buyer,
                            $package->points_awarded,
                            PointTransactionType::PURCHASE,
                            "Achat pack: {$package->name}",
                            $payment->id
                        );
                        Log::info("Points crédités: {$package->points_awarded} pour l'utilisateur {$buyer->id}");
                    }
                }
            }

            // 2. Gestion de l'ÉCHEC ou ANNULATION
            elseif (isset($event['name']) && in_array($event['name'], ['transaction.canceled', 'transaction.declined'])) {
                $payment->forceFill(['status' => PaymentStatus::FAILED])->save();
                Log::info("Paiement #{$payment->id} marqué comme échoué.");
            }

            return response()->json(['status' => 'ok']);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/callback",
     *     summary="3. Retour utilisateur après paiement",
     *     description="Page vers laquelle l'utilisateur est redirigé après avoir quitté l'interface de paiement. Le frontend peut intercepter cette URL pour fermer la WebView.",
     *     tags={"💰 Paiements"},
     *
     *     @OA\Parameter(
     *         name="ad_id",
     *         in="query",
     *         required=true,
     *         description="ID de l'annonce d'origine",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     )
     * )
     */
    public function callback(Request $request): JsonResponse
    {
        $adId = $request->get('ad_id');

        return response()->json([
            'message' => 'Merci pour votre paiement. Votre annonce est en cours de déblocage.',
            'ad_id' => $adId,
            'status' => 'processing',
        ]);
    }

    /**
     * Vérifie le statut d'un paiement auprès de FedaPay et met à jour en base.
     *
     * @OA\Post(
     *     path="/api/v1/payments/verify/{ad}",
     *     summary="Vérifier le statut d'un paiement",
     *     description="Vérifie le paiement le plus récent pour une annonce auprès de FedaPay. Débloque l'annonce si le paiement est approuvé.",
     *     tags={"💰 Paiements"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, description="UUID de l'annonce", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Statut du paiement",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="is_unlocked", type="boolean")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=404, description="Aucun paiement trouvé")
     * )
     */
    public function verify(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $ad) {
            $payment = Payment::where('user_id', $user->id)
                ->where('ad_id', $ad->id)
                ->where('type', PaymentType::UNLOCK)
                ->latest()
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                return response()->json([
                    'message' => 'Aucun paiement trouvé pour cette annonce.',
                    'is_unlocked' => false,
                ], 404);
            }

            if ($payment->status === PaymentStatus::SUCCESS) {
                return response()->json([
                    'message' => 'Annonce déjà débloquée.',
                    'is_unlocked' => true,
                ]);
            }

            $result = $this->fedaPay->retrieveTransaction((int) $payment->transaction_id);

            if ($result['success'] && $result['status'] === 'approved') {
                $payment->forceFill(['status' => PaymentStatus::SUCCESS])->save();

                \App\Models\UnlockedAd::firstOrCreate(
                    ['ad_id' => $ad->id, 'user_id' => $user->id],
                    ['payment_id' => $payment->id, 'unlocked_at' => now()]
                );

                // Send confirmation email — webhook may not have fired yet
                try {
                    $payment->refresh();
                    Mail::to($user->email)->send(new AdUnlockConfirmationMail($user, $ad, $payment));
                } catch (\Exception $e) {
                    Log::error('Erreur envoi email déblocage annonce (verify): '.$e->getMessage());
                }

                Log::info("Paiement #{$payment->id} vérifié et validé via API.");

                return response()->json([
                    'message' => 'Paiement confirmé. Annonce débloquée.',
                    'is_unlocked' => true,
                ]);
            }

            return response()->json([
                'message' => 'Le paiement est en attente de confirmation.',
                'is_unlocked' => false,
                'payment_status' => $result['status'],
            ]);
        });
    }

    private function hasValidWebhookSignature(Request $request, string $secret): bool
    {
        $signatureHeader = trim((string) $request->header('X-Fedapay-Signature', ''));
        if ($signatureHeader === '') {
            return false;
        }

        $payload = $request->getContent();

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $item) {
            $segment = trim($item);
            if ($segment === '') {
                continue;
            }

            if (!str_contains($segment, '=')) {
                $signatures[] = $segment;

                continue;
            }

            [$key, $value] = array_map(trim(...), explode('=', $segment, 2));

            if ($key === 't') {
                $timestamp = $value;

                continue;
            }

            if (in_array($key, ['v1', 's', 'sig', 'signature'], true)) {
                $signatures[] = $value;
            }
        }

        if ($signatures === [] || $timestamp === null) {
            return false;
        }

        // Validate timestamp (prevent replay attacks > 5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning("Webhook FedaPay rejeté: Timestamp expiré (t=$timestamp).");

            return false;
        }

        $expectedTimestampedSignature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return array_any($signatures, fn ($signature) => hash_equals($expectedTimestampedSignature, $signature));
    }
}
