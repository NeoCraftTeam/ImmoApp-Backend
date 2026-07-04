import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { RefundRequest } from '@/types/refund';

interface RefundsResponse {
  data?: RefundRequest[];
}

/** GET /my/refunds. */
export function useRefunds(enabled = true) {
  return useQuery<RefundsResponse, Error, RefundRequest[]>({
    queryKey: ['refunds'],
    queryFn: async () => {
      const { data } = await apiClient.get<RefundsResponse>(
        ENDPOINTS.refunds.list,
        { params: { per_page: 30 } },
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 60 * 1000,
  });
}

/**
 * POST /payments/{payment}/refund-request — demander le remboursement
 * d'un paiement. Le backend prend le montant sur le paiement ; seul
 * `reason` (≥ 10 caractères) est envoyé.
 */
export function useRequestRefund() {
  const qc = useQueryClient();
  return useMutation<
    { refund_id: string; message?: string },
    Error,
    { payment_id: string; reason: string }
  >({
    mutationFn: async ({ payment_id, reason }) => {
      const { data } = await apiClient.post<{ refund_id: string; message?: string }>(
        ENDPOINTS.refunds.request(payment_id),
        { reason },
      );
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['refunds'] }),
  });
}
