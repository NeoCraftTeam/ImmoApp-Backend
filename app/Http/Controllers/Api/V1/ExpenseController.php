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
