<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Ad;
use App\Models\Payment;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Paiements", description="Gestion des paiements FedaPay")
 */
final class PaymentController
{
    private $amount = 500;

    public function __construct(protected FedaPayService $fedaPay) {}

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
    public function initialize(Request $request, Ad $ad)
    {
        $user = $request->user();

        $existingPayment = Payment::where('user_id', $user->id)
            ->where('ad_id', $ad->id)
            ->where('status', PaymentStatus::SUCCESS)
            ->first();

        if ($existingPayment) {
            return response()->json([
                'message' => 'Annonce déjà débloquée.',
                'status' => 'already_paid',
            ]);
        }

        // Sécurité : Le propriétaire n'a pas besoin de payer pour sa propre annonce
        if ($ad->user_id === $user->id) {
            return response()->json([
                'message' => 'Vous êtes le propriétaire de cette annonce.',
                'status' => 'owner',
            ]);
        }

        $paymentData = $this->fedaPay->createPayment($this->amount, $user, $ad->id);

        if ($paymentData['success']) {
            DB::beginTransaction();
            try {
                Payment::create([
                    'user_id' => $user->id,
                    'ad_id' => $ad->id,
                    'amount' => $this->amount,
                    'transaction_id' => (string) $paymentData['transaction_id'],
                    'status' => PaymentStatus::PENDING,
                    'payment_method' => PaymentMethod::FEDAPAY,
                    'type' => PaymentType::UNLOCK,
                ]);

                DB::commit();

                return response()->json([
                    'payment_url' => $paymentData['url'],
                    'message' => 'Redirigez l\'utilisateur vers cette URL pour payer.',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Erreur lors de la création du paiement en base: '.$e->getMessage());

                return response()->json([
                    'message' => 'Erreur technique lors de l\'initialisation.',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'message' => 'Erreur lors de l\'initialisation du paiement.',
            'error' => $paymentData['message'],
        ], 500);
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
    public function webhook(Request $request)
    {
        Log::info('--- WEBHOOK FEDAPAY START ---');
        Log::info('Raw Content: '.$request->getContent());

        $event = $request->all();

        Log::info('FedaPay Webhook reçu:', $event);

        $transactionId = $event['entity']['id'] ?? null;
        if (! $transactionId) {
            return response()->json(['status' => 'error', 'message' => 'No transaction ID'], 400);
        }

        $payment = Payment::where('transaction_id', (string) $transactionId)->first();

        if (! $payment) {
            return response()->json(['status' => 'not_found'], 404);
        }

        DB::beginTransaction();
        try {
            // 1. Gestion du SUCCÈS
            if (isset($event['event']) && $event['event'] === 'transaction.approved') {
                $payment->update(['status' => PaymentStatus::SUCCESS]);
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
            }

            // 2. Gestion de l'ÉCHEC ou ANNULATION
            elseif (isset($event['event']) && in_array($event['event'], ['transaction.canceled', 'transaction.declined'])) {
                $payment->update(['status' => PaymentStatus::FAILED]);
                Log::info("Paiement #{$payment->id} marqué comme échoué.");
            }

            DB::commit();

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors du traitement du webhook: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
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
    public function callback(Request $request)
    {
        $adId = $request->get('ad_id');

        return response()->json([
            'message' => 'Merci pour votre paiement. Votre annonce est en cours de déblocage.',
            'ad_id' => $adId,
            'status' => 'processing',
        ]);
    }
}
