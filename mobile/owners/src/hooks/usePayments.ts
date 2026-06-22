import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { PaymentHistory } from '@/types/payment';

/** GET /payments/history — historique de tous les paiements du bailleur. */
export function usePayments(enabled = true) {
  return useQuery<PaymentHistory>({
    queryKey: ['payments-history'],
    queryFn: async () => {
      const { data } = await apiClient.get<PaymentHistory>(
        ENDPOINTS.payments.history,
        { params: { per_page: 50 } },
      );
      return data;
    },
    enabled,
    staleTime: 60 * 1000,
  });
}
