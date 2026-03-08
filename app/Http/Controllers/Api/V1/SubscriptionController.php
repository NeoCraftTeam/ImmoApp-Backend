<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentType;
use App\Http\Requests\Api\V1\SubscribeRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\SubscriptionPlan;
use App\Services\Payment\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="📦 Abonnements Agences",
 *     description="Gestion des abonnements pour les agences immobilières. Permet de consulter les plans disponibles, souscrire via Flutterwave, consulter l'abonnement actif, l'annuler et voir l'historique. Les agences reçoivent une facture par email à chaque souscription."
 * )
 */
final class SubscriptionController
{
    public function __construct(
        protected PaymentService $paymentService,
        protected SubscriptionService $subscriptionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/subscriptions/plans",
     *     operationId="listSubscriptionPlans",
     *     summary="Lister les plans d'abonnement disponibles",
     *     description="Retourne la liste de tous les plans d'abonnement actifs, triés par ordre de priorité. Chaque plan inclut les tarifs mensuels et annuels, les économies réalisées sur un abonnement annuel, les limites d'annonces, le score de boost, et la liste des fonctionnalités incluses. **Endpoint public** : aucune authentification requise.",
     *     tags={"📦 Abonnements Agences"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste des plans d'abonnement actifs",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="string", format="uuid", example="9e2f3a4b-5c6d-7e8f-9a0b-1c2d3e4f5a6b"),
     *                     @OA\Property(property="name", type="string", example="Premium"),
     *                     @OA\Property(property="slug", type="string", example="premium"),
     *                     @OA\Property(property="description", type="string", example="Plan premium pour les agences en croissance."),
     *                     @OA\Property(property="price_monthly", type="integer", example=35000, description="Prix mensuel en FCFA"),
     *                     @OA\Property(property="price_yearly", type="integer", example=350000, description="Prix annuel en FCFA (null si indisponible)"),
     *                     @OA\Property(property="price_monthly_formatted", type="string", example="35 000 FCFA"),
     *                     @OA\Property(property="price_yearly_formatted", type="string", example="350 000 FCFA"),
     *                     @OA\Property(property="yearly_savings", type="integer", example=70000, description="Économie annuelle en FCFA par rapport au mensuel"),
     *                     @OA\Property(property="duration_days", type="integer", example=30),
     *                     @OA\Property(property="boost_score", type="integer", example=25, description="Points de boost appliqués aux annonces de l'agence"),
     *                     @OA\Property(property="boost_duration_days", type="integer", example=14, description="Durée du boost en jours"),
     *                     @OA\Property(property="max_ads", type="integer", nullable=true, example=50, description="Nombre max d'annonces (null = illimité)"),
     *                     @OA\Property(property="is_unlimited", type="boolean", example=false),
     *                     @OA\Property(property="features", type="array", @OA\Items(type="string", example="Boost de +25 points pendant 14 jours")),
     *                     @OA\Property(property="sort_order", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function plans(): AnonymousResourceCollection
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return SubscriptionPlanResource::collection($plans);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/subscriptions/current",
     *     operationId="currentSubscription",
     *     summary="Consulter l'abonnement actif de mon agence",
     *     description="Retourne l'abonnement actuellement actif de l'agence à laquelle appartient l'utilisateur authentifié, avec les détails du plan, le nombre de jours restants, et des statistiques (nombre d'annonces boostées, etc.). Si aucun abonnement n'est actif, `has_subscription` vaut `false` et `subscription` est `null`.",
     *     tags={"📦 Abonnements Agences"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Abonnement actif trouvé (ou aucun abonnement)",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="has_subscription", type="boolean", example=true),
     *             @OA\Property(
     *                 property="subscription",
     *                 nullable=true,
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="plan", type="object", description="Détails du plan souscrit"),
     *                 @OA\Property(property="billing_period", type="string", enum={"monthly", "yearly"}, example="monthly"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "active", "expired", "cancelled"}, example="active"),
     *                 @OA\Property(property="amount_paid", type="integer", example=35000),
     *                 @OA\Property(property="amount_paid_formatted", type="string", example="35 000 FCFA"),
     *                 @OA\Property(property="starts_at", type="string", format="date-time"),
     *                 @OA\Property(property="ends_at", type="string", format="date-time"),
     *                 @OA\Property(property="days_remaining", type="integer", example=24),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_renew", type="boolean", example=false)
     *             ),
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="has_active_subscription", type="boolean", example=true),
     *                 @OA\Property(property="current_plan", type="string", example="Premium"),
     *                 @OA\Property(property="days_remaining", type="integer", example=24),
     *                 @OA\Property(property="expires_at", type="string", format="date-time"),
     *                 @OA\Property(property="total_boosted_ads", type="integer", example=12)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié — Token Sanctum manquant ou invalide"),
     *     @OA\Response(
     *         response=403,
     *         description="L'utilisateur n'appartient à aucune agence",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Vous n'appartenez à aucune agence."))
     *     )
     * )
     */
    public function current(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var \App\Models\Agency|null $agency */
        $agency = $user->agency;

        if (!$agency) {
            return response()->json([
                'message' => 'Vous n\'appartenez à aucune agence.',
            ], 403);
        }

        $subscription = $agency->getCurrentSubscription();

        if (!$subscription) {
            return response()->json([
                'has_subscription' => false,
                'subscription' => null,
            ]);
        }

        $subscription->load('plan');

        return response()->json([
            'has_subscription' => true,
            'subscription' => new SubscriptionResource($subscription),
            'stats' => $this->subscriptionService->getAgencyStats($agency),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/subscriptions/subscribe",
     *     operationId="subscribe",
     *     summary="Souscrire à un plan d'abonnement",
     *     description="Initie le processus de souscription à un plan d'abonnement pour l'agence de l'utilisateur. Crée une transaction de paiement et retourne l'URL de paiement. Le frontend (mobile ou web) doit **rediriger l'utilisateur** vers `payment_url` pour finaliser le paiement. Une fois le paiement confirmé par le webhook, l'abonnement est activé automatiquement, les annonces de l'agence sont boostées, et une **facture est envoyée par email** à tous les membres de l'agence.",
     *     tags={"📦 Abonnements Agences"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de souscription",
     *
     *         @OA\JsonContent(
     *             required={"plan_id", "billing_period"},
     *
     *             @OA\Property(property="plan_id", type="string", format="uuid", description="UUID du plan choisi (obtenu via GET /subscriptions/plans)", example="9e2f3a4b-5c6d-7e8f-9a0b-1c2d3e4f5a6b"),
     *             @OA\Property(property="billing_period", type="string", enum={"monthly", "yearly"}, description="Période de facturation : mensuel ou annuel (annuel = ~2 mois offerts)", example="monthly"),
     *             @OA\Property(property="callback_url", type="string", description="URL de retour après paiement (optionnel, par défaut l'URL du frontend)", example="https://app.keyhome.cm/subscription/callback")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Paiement initialisé — Redirigez l'utilisateur vers payment_url",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="payment_url", type="string", description="URL vers laquelle rediriger l'utilisateur pour payer", example="https://checkout.flutterwave.com/pay/abc123"),
     *             @OA\Property(property="message", type="string", example="Redirigez l'utilisateur vers cette URL pour payer.")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(
     *         response=403,
     *         description="L'utilisateur n'appartient à aucune agence",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Vous devez appartenir à une agence pour souscrire."))
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Données invalides ou plan indisponible",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ce plan n'est plus disponible."),
     *             @OA\Property(property="errors", type="object", description="Détails des erreurs de validation (si applicable)")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Erreur technique serveur")
     * )
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var \App\Models\Agency|null $agency */
        $agency = $user->agency;

        if (!$agency) {
            return response()->json([
                'message' => 'Vous devez appartenir à une agence pour souscrire.',
            ], 403);
        }

        $plan = SubscriptionPlan::findOrFail($request->validated('plan_id'));
        $period = $request->validated('billing_period');

        if (!$plan->is_active) {
            return response()->json([
                'message' => 'Ce plan n\'est plus disponible.',
            ], 422);
        }

        $amount = $period === 'yearly' ? (int) $plan->price_yearly : (int) $plan->price;

        if ($amount <= 0) {
            return response()->json([
                'message' => 'Tarification indisponible pour cette période.',
            ], 422);
        }

        try {
            $result = $this->paymentService->createPayment($user, [
                'amount' => (float) $amount,
                'type' => PaymentType::SUBSCRIPTION->value,
                'payment_method' => 'flutterwave',
                'agency_id' => $agency->id,
                'plan_id' => $plan->id,
                'period' => $period,
                'description' => "Abonnement {$plan->name} ({$period})",
                'meta' => [
                    'payment_type' => 'subscription',
                    'agency_id' => $agency->id,
                    'plan_id' => $plan->id,
                    'period' => $period,
                ],
            ]);

            return response()->json([
                'payment_url' => $result['link'],
                'message' => 'Redirigez l\'utilisateur vers cette URL pour payer.',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur création paiement abonnement: '.$e->getMessage());

            return response()->json([
                'message' => 'Erreur technique lors de l\'initialisation.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/subscriptions/cancel",
     *     operationId="cancelSubscription",
     *     summary="Annuler l'abonnement actif de mon agence",
     *     description="Annule l'abonnement actif de l'agence. **L'abonnement reste fonctionnel jusqu'à sa date d'expiration** (`ends_at`), mais ne sera pas renouvelé. L'agence conserve donc l'accès aux fonctionnalités premium jusqu'à la fin de la période payée. Une raison d'annulation peut être fournie (optionnel).",
     *     tags={"📦 Abonnements Agences"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=false,
     *         description="Raison de l'annulation (optionnel)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="reason", type="string", description="Raison de l'annulation", example="Nous n'avons plus besoin du service pour le moment.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Abonnement annulé avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Votre abonnement a été annulé. Il reste actif jusqu'au 15/03/2026."),
     *             @OA\Property(property="subscription", type="object", description="Détails de l'abonnement annulé")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(
     *         response=403,
     *         description="L'utilisateur n'appartient à aucune agence",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Vous n'appartenez à aucune agence."))
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Aucun abonnement actif trouvé pour cette agence",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Aucun abonnement actif à annuler."))
     *     )
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var \App\Models\Agency|null $agency */
        $agency = $user->agency;

        if (!$agency) {
            return response()->json([
                'message' => 'Vous n\'appartenez à aucune agence.',
            ], 403);
        }

        $subscription = $agency->getCurrentSubscription();

        if (!$subscription) {
            return response()->json([
                'message' => 'Aucun abonnement actif à annuler.',
            ], 404);
        }

        $reason = $request->input('reason', 'Annulé par l\'utilisateur via l\'API');
        $subscription->cancel($reason);

        return response()->json([
            'message' => 'Votre abonnement a été annulé. Il reste actif jusqu\'au '
                .$subscription->ends_at->format('d/m/Y').'. ',
            'subscription' => new SubscriptionResource($subscription->load('plan')),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/subscriptions/history",
     *     operationId="subscriptionHistory",
     *     summary="Historique des abonnements de mon agence",
     *     description="Retourne l'historique paginé de tous les abonnements (actifs, expirés, annulés) de l'agence à laquelle appartient l'utilisateur. Chaque entrée inclut les détails du plan, la période de facturation, le montant payé et les dates. Utile pour afficher un récapitulatif ou un historique de facturation dans l'application mobile ou web.",
     *     tags={"📦 Abonnements Agences"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Numéro de page pour la pagination (15 éléments par page)",
     *
     *         @OA\Schema(type="integer", default=1, example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Historique paginé des abonnements",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="plan", type="object", description="Détails du plan souscrit"),
     *                     @OA\Property(property="billing_period", type="string", enum={"monthly", "yearly"}),
     *                     @OA\Property(property="status", type="string", enum={"pending", "active", "expired", "cancelled"}),
     *                     @OA\Property(property="amount_paid", type="integer", example=35000),
     *                     @OA\Property(property="amount_paid_formatted", type="string", example="35 000 FCFA"),
     *                     @OA\Property(property="starts_at", type="string", format="date-time"),
     *                     @OA\Property(property="ends_at", type="string", format="date-time"),
     *                     @OA\Property(property="days_remaining", type="integer"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 description="Liens de pagination",
     *                 @OA\Property(property="first", type="string"),
     *                 @OA\Property(property="last", type="string"),
     *                 @OA\Property(property="prev", type="string", nullable=true),
     *                 @OA\Property(property="next", type="string", nullable=true)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 description="Métadonnées de pagination",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=42)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(
     *         response=403,
     *         description="L'utilisateur n'appartient à aucune agence",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Vous n'appartenez à aucune agence."))
     *     )
     * )
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var \App\Models\Agency|null $agency */
        $agency = $user->agency;

        if (!$agency) {
            abort(403, 'Vous n\'appartenez à aucune agence.');
        }

        $subscriptions = $agency->subscriptions()
            ->with('plan')
            ->latest()
            ->paginate(15);

        return SubscriptionResource::collection($subscriptions);
    }
}
