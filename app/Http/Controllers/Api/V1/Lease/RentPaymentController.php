<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lease;

use App\Http\Requests\Api\V1\StoreRentPaymentRequest;
use App\Http\Requests\Api\V1\UpdateRentPaymentRequest;
use App\Http\Resources\RentPaymentResource;
use App\Models\LeaseContract;
use App\Models\Payment;
use App\Models\RentPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Rent collection ledger — landlord records actual rent received per
 * lease contract. See {@see RentPayment} for why this is separate from
 * the {@see Payment} table.
 */
final class RentPaymentController
{
    /**
     * @OA\Get(
     *     path="/api/v1/my/lease-contracts/{leaseContract}/rent-payments",
     *     summary="Lister les loyers encaissés d'un bail",
     *     description="Retourne la liste paginée des paiements de loyer enregistrés pour le bail, triés du plus récent au plus ancien.",
     *     operationId="listRentPayments",
     *     tags={"📊 Loyers"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="leaseContract", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *
     *     @OA\Response(response=200, description="Liste paginée avec meta et links"),
     *     @OA\Response(response=403, description="Accès refusé (pas votre bail)")
     * )
     */
    public function index(LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $payments = RentPayment::query()
            ->where('lease_contract_id', $leaseContract->id)
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Mirror ExpenseController's `{ data, meta, links }` envelope so
        // the owner financials page paginates uniformly across both
        // ledgers.
        return response()->json([
            'data' => RentPaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
            'links' => [
                'first' => $payments->url(1),
                'last' => $payments->url($payments->lastPage()),
                'prev' => $payments->previousPageUrl(),
                'next' => $payments->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/my/lease-contracts/{leaseContract}/rent-payments",
     *     summary="Enregistrer un loyer encaissé",
     *     operationId="storeRentPayment",
     *     tags={"📊 Loyers"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="leaseContract", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"period_month","amount","payment_method","received_at"},
     *
     *         @OA\Property(property="period_month", type="string", format="date", example="2026-05-01", description="Mois locatif couvert (1er du mois)"),
     *         @OA\Property(property="amount", type="integer", example=150000, description="Montant en XAF"),
     *         @OA\Property(property="payment_method", type="string", example="mobile_money", description="cash | mobile_money | bank_transfer | other"),
     *         @OA\Property(property="received_at", type="string", format="date", example="2026-05-03"),
     *         @OA\Property(property="notes", type="string", nullable=true, example="Reçu via Orange Money — réf. OM-12345")
     *     )),
     *
     *     @OA\Response(response=201, description="Paiement enregistré"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(StoreRentPaymentRequest $request, LeaseContract $leaseContract): JsonResponse
    {
        if ($leaseContract->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validated();

        // Normalise the period to the first day of its month so partial
        // payments aggregate cleanly when summed per (lease, period_month).
        $validated['period_month'] = Carbon::parse($validated['period_month'])->startOfMonth()->toDateString();
        $validated['lease_contract_id'] = $leaseContract->id;
        $validated['recorded_by_user_id'] = auth()->id();

        $payment = RentPayment::query()->create($validated);

        return response()->json(['data' => new RentPaymentResource($payment)], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/my/rent-payments/{rentPayment}",
     *     summary="Mettre à jour un loyer encaissé",
     *     operationId="updateRentPayment",
     *     tags={"📊 Loyers"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="rentPayment", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Paiement mis à jour"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function update(UpdateRentPaymentRequest $request, RentPayment $rentPayment): JsonResponse
    {
        if (!$this->landlordOwns($rentPayment)) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $validated = $request->validated();

        if (array_key_exists('period_month', $validated)) {
            $validated['period_month'] = Carbon::parse($validated['period_month'])->startOfMonth()->toDateString();
        }

        $rentPayment->update($validated);

        return response()->json(['data' => new RentPaymentResource($rentPayment->fresh())]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/my/rent-payments/{rentPayment}",
     *     summary="Supprimer un loyer encaissé",
     *     operationId="destroyRentPayment",
     *     tags={"📊 Loyers"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="rentPayment", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Paiement supprimé"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function destroy(RentPayment $rentPayment): JsonResponse
    {
        if (!$this->landlordOwns($rentPayment)) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $rentPayment->delete();

        return response()->json(['message' => 'Loyer supprimé.']);
    }

    /**
     * Ownership check via the parent lease contract — the landlord that
     * owns the lease is the only one allowed to mutate the ledger row
     * (the `recorded_by_user_id` could be an agent acting on the
     * landlord's behalf in a future iteration, so we don't gate on it).
     */
    private function landlordOwns(RentPayment $rentPayment): bool
    {
        return $rentPayment->leaseContract()->where('user_id', auth()->id())->exists();
    }
}
