import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Expense, ExpenseCategory } from '@/types/owner';

interface ExpensesResponse {
  data?: Expense[];
}

interface ProfitLossResponse {
  income: number;
  expenses: number;
  net: number;
  currency?: string;
  by_category?: Record<ExpenseCategory, number>;
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
  return useQuery<ProfitLossResponse, Error, ProfitLossResponse>({
    queryKey: ['profit-loss', adId],
    queryFn: async () => {
      if (!adId) {
        return { income: 0, expenses: 0, net: 0 };
      }
      const { data } = await apiClient.get<ProfitLossResponse>(
        ENDPOINTS.my.profitLoss(adId),
      );
      return data;
    },
    enabled: enabled && !!adId,
    staleTime: 60 * 1000,
  });
}

export function useCreateExpense(adId: string) {
  const qc = useQueryClient();
  return useMutation<
    Expense,
    Error,
    { category: ExpenseCategory; amount: number; date: string; description?: string }
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
