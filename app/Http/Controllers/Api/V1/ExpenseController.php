<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Models\Ad;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;

/**
 * Expense tracking per property for landlords.
 */
final class ExpenseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/expenses",
     *     summary="Lister les dépenses d'un bien",
     *     description="Retourne les dépenses (maintenance, travaux, charges) liées à l'annonce du bailleur, triées par date.",
     *     operationId="listExpenses",
     *     tags={"📊 Dépenses"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *
     *     @OA\Response(response=200, description="Liste paginée de dépenses avec meta et links"),
     *     @OA\Response(response=403, description="Accès refusé (pas votre bien)")
     * )
     */
    public function index(Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $expenses = Expense::query()
            ->where('ad_id', $ad->id)
            ->orderByDesc('expense_date')
            ->paginate(20);

        // Wrap in `{ data, meta, links }` so the owner financials page (which
        // reads `expensesData?.meta`) shows the MUI `<Pagination>` correctly.
        // Without this, Laravel's default paginator JSON shape exposes
        // `current_page` / `last_page` at the root and `meta` is `undefined`,
        // so the pagination component never renders.
        return response()->json([
            'data' => $expenses->items(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
            'links' => [
                'first' => $expenses->url(1),
                'last' => $expenses->url($expenses->lastPage()),
                'prev' => $expenses->previousPageUrl(),
                'next' => $expenses->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/ads/{ad}/expenses",
     *     summary="Enregistrer une dépense",
     *     operationId="storeExpense",
     *     tags={"📊 Dépenses"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"category","amount","expense_date"},
     *
     *         @OA\Property(property="category", type="string", example="maintenance", description="Catégorie (maintenance, travaux, charges, assurance, other)"),
     *         @OA\Property(property="amount", type="number", example=25000, description="Montant en XAF"),
     *         @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15"),
     *         @OA\Property(property="description", type="string", nullable=true, example="Réparation toiture")
     *     )),
     *
     *     @OA\Response(response=201, description="Dépense enregistrée"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(StoreExpenseRequest $request, Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validated();

        $validated['ad_id'] = $ad->id;
        $validated['user_id'] = auth()->id();

        $expense = Expense::query()->create($validated);

        return response()->json(['data' => $expense], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/expenses/{expense}",
     *     summary="Supprimer une dépense",
     *     operationId="destroyExpense",
     *     tags={"📊 Dépenses"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="expense", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Dépense supprimée"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function destroy(Expense $expense): JsonResponse
    {
        if ($expense->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $expense->delete();

        return response()->json(['message' => 'Dépense supprimée.']);
    }

    /**
     * Returns revenue vs expense (profit/loss) summary for an ad.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{ad}/expenses/profit-loss",
     *     summary="Bilan recettes / dépenses",
     *     description="Retourne le total des dépenses, les revenus issus des contrats de bail et le résultat net pour un bien.",
     *     operationId="profitLoss",
     *     tags={"📊 Dépenses"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="ad", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Bilan financier", @OA\JsonContent(
     *
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="total_expenses", type="number", example=125000),
     *             @OA\Property(property="contract_revenue", type="number", example=300000),
     *             @OA\Property(property="net_income", type="number", example=175000),
     *             @OA\Property(property="expenses_by_category", type="object")
     *         )
     *     )),
     *
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function profitLoss(Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $totalExpenses = Expense::query()
            ->where('ad_id', $ad->id)
            ->sum('amount');

        $contractRevenue = $ad->leaseContracts()
            ->sum('monthly_rent');

        return response()->json([
            'data' => [
                'total_expenses' => (float) $totalExpenses,
                'contract_revenue' => (float) $contractRevenue,
                'net_income' => (float) ($contractRevenue - $totalExpenses),
                'expenses_by_category' => Expense::query()
                    ->where('ad_id', $ad->id)
                    ->selectRaw('category, SUM(amount) as total')
                    ->groupBy('category')
                    ->pluck('total', 'category'),
            ],
        ]);
    }
}
