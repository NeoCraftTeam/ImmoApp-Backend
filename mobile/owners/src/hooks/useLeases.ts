import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { LeaseContract } from '@/types/owner';

/** GET /my/lease-contracts. */
export function useLeases(enabled = true) {
  return useQuery<{ data: LeaseContract[] }, Error, LeaseContract[]>({
    queryKey: ['leases'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: LeaseContract[] }>(
        ENDPOINTS.my.leaseContracts,
        { params: { per_page: 30 } },
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}
