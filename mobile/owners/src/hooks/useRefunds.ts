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

/** POST /my/refunds — créer une demande de remboursement. */
export function useRequestRefund() {
  const qc = useQueryClient();
  return useMutation<
    RefundRequest,
    Error,
    { payment_id?: string; amount: number; reason: string }
  >({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ data: RefundRequest }>(
        ENDPOINTS.refunds.request,
        payload,
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['refunds'] }),
  });
}
