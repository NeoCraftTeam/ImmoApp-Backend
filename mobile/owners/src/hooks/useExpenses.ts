import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Expense, ExpenseCategory } from '@/types/owner';

interface ExpensesResponse {
  data?: Expense[];
}

/** Forme renvoyée par ExpenseController@profitLoss, enveloppée dans `data`. */
interface ProfitLossResponse {
  contract_revenue: number;
  total_expenses: number;
  net_income: number;
  currency?: string;
  expenses_by_category?: Partial<Record<ExpenseCategory, number>>;
}

export function useExpenses(adId: string | undefined, enabled = true) {
  return useQuery<ExpensesResponse, Error, Expense[]>({
    queryKey: ['expenses', adId],
    queryFn: async () => {
      if (!adId) return { data: [] };
      const { data } = await apiClient.get<ExpensesResponse>(
        ENDPOINTS.my.expenses(adId),
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled: enabled && !!adId,
    staleTime: 60 * 1000,
  });
}

export function useProfitLoss(adId: string | undefined, enabled = true) {
  return useQuery<{ data: ProfitLossResponse }, Error, ProfitLossResponse>({
    queryKey: ['profit-loss', adId],
    queryFn: async () => {
      if (!adId) {
        return { data: { contract_revenue: 0, total_expenses: 0, net_income: 0 } };
      }
      const { data } = await apiClient.get<{ data: ProfitLossResponse }>(
        ENDPOINTS.my.profitLoss(adId),
      );
      return data;
    },
    select: (p) => p.data,
    enabled: enabled && !!adId,
    staleTime: 60 * 1000,
  });
}

export function useCreateExpense(adId: string) {
  const qc = useQueryClient();
  return useMutation<
    Expense,
    Error,
    { category: ExpenseCategory; amount: number; expense_date: string; description?: string }
  >({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ data: Expense }>(
        ENDPOINTS.my.expenses(adId),
        payload,
      );
      return data.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['expenses', adId] });
      qc.invalidateQueries({ queryKey: ['profit-loss', adId] });
    },
  });
}

export function useDeleteExpense(adId: string) {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.delete(ENDPOINTS.my.expense(id));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['expenses', adId] });
      qc.invalidateQueries({ queryKey: ['profit-loss', adId] });
    },
  });
}
