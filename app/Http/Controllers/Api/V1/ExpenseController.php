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
    public function index(Ad $ad): JsonResponse
    {
        if ($ad->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $expenses = Expense::query()
            ->where('ad_id', $ad->id)
            ->orderByDesc('expense_date')
            ->paginate(20);

        return response()->json($expenses);
    }

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
