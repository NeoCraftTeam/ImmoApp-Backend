import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { NeighborhoodScorecard } from '@/types/scorecard';

export function useNeighborhoodScorecard(adId: string | undefined) {
  return useQuery<{ data: NeighborhoodScorecard } | NeighborhoodScorecard, Error, NeighborhoodScorecard>({
    queryKey: ['ad-scorecard', adId],
    queryFn: async () => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.get(ENDPOINTS.ads.scorecard(adId));
      return data;
    },
    select: (payload) =>
      ('data' in (payload as { data?: unknown })
        ? (payload as { data: NeighborhoodScorecard }).data
        : (payload as NeighborhoodScorecard)) ?? {
        overall_score: 0,
        categories: [],
        unavailable: true,
      },
    enabled: Boolean(adId),
    staleTime: 24 * 60 * 60 * 1000,
  });
}
