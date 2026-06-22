import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { PaymentTransaction, PaymentsResponse } from '@/types/payment';

export function usePayments() {
  return useQuery<PaymentsResponse, Error, PaymentTransaction[]>({
    queryKey: ['payments-history'],
    queryFn: async () => {
      const { data } = await apiClient.get<PaymentsResponse>(
        ENDPOINTS.payments.history,
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 60 * 1000,
  });
}

export function useCreditsBalance() {
  return useQuery<{ balance: number } | number, Error, number>({
    queryKey: ['credits-balance'],
    queryFn: async () => {
      const { data } = await apiClient.get(ENDPOINTS.credits.balance);
      return data;
    },
    select: (payload) => {
      if (typeof payload === 'number') return payload;
      if (payload && typeof payload === 'object' && 'balance' in payload) {
        return (payload as { balance: number }).balance ?? 0;
      }
      if (
        payload &&
        typeof payload === 'object' &&
        'data' in payload &&
        typeof (payload as { data?: { balance?: number } }).data?.balance === 'number'
      ) {
        return (payload as { data: { balance: number } }).data.balance;
      }
      return 0;
    },
    staleTime: 30 * 1000,
  });
}
