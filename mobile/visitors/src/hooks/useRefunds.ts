import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { queryKeys } from '@/lib/query-keys';
import type { Refund, RefundListResponse } from '@/types/refund';

export function useRefunds() {
  return useQuery<RefundListResponse, Error, Refund[]>({
    queryKey: ['refunds'],
    queryFn: async () => {
      const { data } = await apiClient.get<RefundListResponse>(
        ENDPOINTS.payments.refunds,
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 60 * 1000,
  });
}

export function useRequestRefund() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { paymentId: string; reason: string }) => {
      const { data } = await apiClient.post(
        ENDPOINTS.payments.refundRequest(input.paymentId),
        { reason: input.reason },
      );
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['refunds'] });
      qc.invalidateQueries({ queryKey: queryKeys.paymentsHistory() });
    },
  });
}
