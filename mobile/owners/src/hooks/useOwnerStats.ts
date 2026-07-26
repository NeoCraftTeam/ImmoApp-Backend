import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { OwnerStats } from '@/types/owner';

/**
 * Dashboard KPI summary — GET /my/stats. Returns occupancy, active
 * boosts, pending viewings, revenue, and a status breakdown.
 */
export function useOwnerStats(enabled = true) {
  return useQuery<{ data: OwnerStats }, Error, OwnerStats>({
    queryKey: ['owner-stats'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: OwnerStats }>(ENDPOINTS.my.stats);
      return data;
    },
    select: (payload) => payload.data,
    enabled,
    staleTime: 60 * 1000,
  });
}
