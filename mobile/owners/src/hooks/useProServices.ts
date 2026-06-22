import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { ProService } from '@/types/proservice';

interface ProServicesResponse {
  data?: ProService[];
}

/** GET /pro-services — catalogue des services pro disponibles. */
export function useProServices(enabled = true) {
  return useQuery<ProServicesResponse, Error, ProService[]>({
    queryKey: ['pro-services'],
    queryFn: async () => {
      const { data } = await apiClient.get<ProServicesResponse>(
        ENDPOINTS.proServices.list,
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}

export function usePurchaseProService() {
  const qc = useQueryClient();
  return useMutation<void, Error, { service_id: string; ad_id?: string }>({
    mutationFn: async (payload) => {
      await apiClient.post(ENDPOINTS.proServices.purchase, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pro-services'] });
      qc.invalidateQueries({ queryKey: ['credits-balance'] });
    },
  });
}
