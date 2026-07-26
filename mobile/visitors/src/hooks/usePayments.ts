import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { queryKeys } from '@/lib/query-keys';
import type { PaymentTransaction, PaymentsResponse } from '@/types/payment';
import { parseCreditsBalance } from '@/utils/credits-balance';
import { normalizePaymentHistoryList } from '@/utils/payment-history';

export function usePayments(enabled = true) {
  return useQuery<PaymentsResponse, Error, PaymentTransaction[]>({
    queryKey: queryKeys.paymentsHistory(),
    queryFn: async () => {
      const { data } = await apiClient.get<PaymentsResponse>(
        ENDPOINTS.payments.history,
      );
      return data;
    },
    select: (payload) => normalizePaymentHistoryList(payload),
    enabled,
    staleTime: 0,
    refetchOnMount: 'always',
    refetchOnReconnect: true,
  });
}

export function useCreditsBalance(enabled = true) {
  return useQuery<Record<string, unknown> | number, Error, number>({
    queryKey: queryKeys.creditsBalance(),
    queryFn: async () => {
      const { data } = await apiClient.get(ENDPOINTS.credits.balance);
      return data;
    },
    enabled,
    select: parseCreditsBalance,
    staleTime: 0,
    refetchOnMount: 'always',
    refetchOnReconnect: true,
  });
}
