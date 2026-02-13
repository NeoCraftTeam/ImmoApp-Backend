<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Requests\Api\V1\SubscribeRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\FedaPayService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SubscriptionController
{
    public function __construct(
        protected FedaPayService $fedaPay,
        protected SubscriptionService $subscriptionService,
    ) {}

    /**
     * List all active subscription plans.
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
     * Get the authenticated agency's current subscription.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
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
     * Subscribe to a plan — initiate FedaPay payment.
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $user = $request->user();
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

        $callbackUrl = $request->input(
            'callback_url',
            config('app.frontend_url', config('app.url')).'/subscription/callback'
        );

        $paymentData = $this->fedaPay->createSubscriptionPayment(
            $amount,
            $agency,
            $plan->id,
            $period,
            $callbackUrl,
        );

        if (!$paymentData['success']) {
            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement.',
                'error' => config('app.debug') ? ($paymentData['message'] ?? null) : null,
            ], 500);
        }

        DB::beginTransaction();
        try {
            Payment::create([
                'user_id' => $user->id,
                'agency_id' => $agency->id,
                'plan_id' => $plan->id,
                'period' => $period,
                'amount' => $amount,
                'transaction_id' => (string) $paymentData['transaction_id'],
                'status' => PaymentStatus::PENDING,
                'payment_method' => PaymentMethod::FEDAPAY,
                'type' => PaymentType::SUBSCRIPTION,
            ]);

            DB::commit();

            return response()->json([
                'payment_url' => $paymentData['url'],
                'message' => 'Redirigez l\'utilisateur vers cette URL pour payer.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création paiement abonnement: '.$e->getMessage());

            return response()->json([
                'message' => 'Erreur technique lors de l\'initialisation.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cancel the current active subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
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
     * Get subscription history for the agency.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
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
