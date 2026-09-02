<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Requests\RefundRequest;
use App\Http\Requests\RequestRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

final readonly class RefundController
{
    public function __construct(
        private RefundService $refundService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/admin/payments/{payment}/refund",
     *     summary="Initiate a refund for a payment (admin only)",
     *     tags={"Admin - Payments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="payment", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"reason"},
     *
     *         @OA\Property(property="reason", type="string", example="Client mécontent du service"),
     *         @OA\Property(property="amount", type="number", example=5000),
     *         @OA\Property(property="admin_note", type="string", example="Remboursement exceptionnel")
     *     )),
     *
     *     @OA\Response(response=200, description="Refund processed"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or payment not refundable")
     * )
     */
    public function store(RefundRequest $request, Payment $payment): JsonResponse
    {
        try {
            $refund = $this->refundService->processRefund(
                $payment,
                $request->user(),
                $request->validated(),
            );

            return response()->json([
                'message' => 'Remboursement traité avec succès.',
                'refund' => [
                    'id' => $refund->id,
                    'amount' => $refund->amount,
                    'status' => $refund->status->value,
                    'is_partial' => $refund->is_partial,
                    'gateway_refund_id' => $refund->gateway_refund_id,
                    'side_effects_reversed' => $refund->side_effects_reversed,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Refund rejected', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Le remboursement n\'a pas pu être traité. Vérifiez les conditions requises.'], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/{payment}/refund-request",
     *     summary="Self-service refund request (user-facing). Creates a Pending refund for admin review.",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="payment", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"reason"},
     *
     *         @OA\Property(property="reason", type="string", example="Service non rendu")
     *     )),
     *
     *     @OA\Response(response=201, description="Refund request created"),
     *     @OA\Response(response=403, description="Payment does not belong to user"),
     *     @OA\Response(response=422, description="Already has a pending or completed refund")
     * )
     */
    public function requestRefund(RequestRefundRequest $request, Payment $payment): JsonResponse
    {
        if ($payment->user_id !== $request->user()->id) {
            abort(403, 'Accès refusé.');
        }

        $existing = $payment->refunds()
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Une demande de remboursement est déjà en cours pour ce paiement.',
            ], 422);
        }

        $validated = $request->validated();

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'user_id' => $request->user()->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'XAF',
            'reason' => $validated['reason'],
            'is_partial' => false,
        ]);

        return response()->json([
            'message' => 'Votre demande de remboursement a été envoyée à notre équipe.',
            'refund_id' => $refund->id,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/refunds",
     *     summary="List authenticated user's own refunds",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Paginated list of refunds")
     * )
     */
    public function userRefunds(Request $request): AnonymousResourceCollection
    {
        $refunds = Refund::where('user_id', $request->user()->id)
            ->with(['payment:id,type,amount,created_at'])
            ->latest()
            ->paginate(15);

        return RefundResource::collection($refunds);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/payments/{payment}/refunds",
     *     summary="List refunds for a payment (admin only)",
     *     tags={"Admin - Payments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="payment", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="List of refunds")
     * )
     */
    public function index(Payment $payment): JsonResponse
    {
        $refunds = $payment->refunds()
            ->with('processedBy:id,firstname,lastname')
            ->latest()
            ->get();

        return response()->json(['data' => $refunds]);
    }
}
