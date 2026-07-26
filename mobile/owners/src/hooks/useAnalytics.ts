import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { OwnerAnalytics } from '@/types/owner';

/** GET /my/ads/analytics?period= — owner-wide analytics overview. */
export function useAnalytics(period: '7d' | '30d' | '90d' = '30d', enabled = true) {
  return useQuery<{ data: OwnerAnalytics }, Error, OwnerAnalytics>({
    queryKey: ['analytics', period],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: OwnerAnalytics }>(
        ENDPOINTS.my.adsAnalytics,
        { params: { period } },
      );
      return data;
    },
    select: (p) => p.data,
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}
