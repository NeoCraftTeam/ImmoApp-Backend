import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { TrustScore } from '@/types/proservice';

/** GET /my/trust-score — détail des facteurs de confiance + recos. */
export function useTrustScore(enabled = true) {
  return useQuery<{ data: TrustScore } | TrustScore, Error, TrustScore | null>({
    queryKey: ['trust-score'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: TrustScore } | TrustScore>(
        ENDPOINTS.trust.score,
      );
      return data;
    },
    select: (p) => {
      if (!p) return null;
      const inner = (p as { data?: TrustScore }).data;
      return inner ?? (p as TrustScore);
    },
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}
