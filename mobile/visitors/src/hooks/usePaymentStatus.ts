import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export interface PublicPaymentStatus {
  status: 'pending' | 'success' | 'failed' | string;
  amount?: number;
  currency?: string;
  ad_slug?: string | null;
  ad_id?: string | null;
  message?: string;
}

export function usePublicPaymentStatus(txRef: string | undefined) {
  return useQuery<{ data: PublicPaymentStatus } | PublicPaymentStatus, Error, PublicPaymentStatus>({
    queryKey: ['payment-status', txRef],
    queryFn: async () => {
      if (!txRef) throw new Error('Missing tx_ref');
      const { data } = await apiClient.get(ENDPOINTS.payments.publicStatus(txRef));
      return data;
    },
    select: (payload) =>
      ('data' in (payload as { data?: unknown })
        ? (payload as { data: PublicPaymentStatus }).data
        : (payload as PublicPaymentStatus)),
    enabled: Boolean(txRef),
    refetchInterval: (q) => {
      const status = q.state.data
        ? (q.state.data as PublicPaymentStatus | undefined)?.status
        : undefined;
      // Keep polling while pending; stop when terminal.
      return status === 'pending' ? 3000 : false;
    },
  });
}
