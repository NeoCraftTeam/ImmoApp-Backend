import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad } from '@/types/ad';

/**
 * Fetch a single ad by slug or UUID. The backend accepts both via
 * implicit route-model binding; we encode the input so a slug with
 * spaces / accented chars round-trips correctly.
 */
export function useAd(slugOrId: string | undefined) {
  return useQuery<{ data: Ad }, Error, Ad>({
    queryKey: ['ad', slugOrId],
    queryFn: async () => {
      if (!slugOrId) throw new Error('Missing ad slug/id');
      const { data } = await apiClient.get<{ data: Ad }>(ENDPOINTS.ads.detail(slugOrId));
      return data;
    },
    select: (payload) => payload.data,
    enabled: Boolean(slugOrId),
    staleTime: 5 * 60 * 1000,
  });
}
