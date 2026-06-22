import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad } from '@/types/ad';

/**
 * Single ad detail — GET /ads/{id}. Owner view (includes draft_payload,
 * counters, charges). Wraps the standard `{ data }` envelope.
 */
export function useAd(idOrSlug: string | undefined, enabled = true) {
  return useQuery<{ data: Ad }, Error, Ad>({
    queryKey: ['ad', idOrSlug],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Ad }>(
        ENDPOINTS.ads.detail(idOrSlug as string),
      );
      return data;
    },
    select: (payload) => payload.data,
    enabled: enabled && !!idOrSlug,
    staleTime: 60 * 1000,
  });
}
