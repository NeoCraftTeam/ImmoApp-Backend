import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { KeyScorePayload } from '@/types/keyscore';

export function useKeyScore(adId: string | undefined) {
  return useQuery<{ data: KeyScorePayload } | KeyScorePayload, Error, KeyScorePayload>({
    queryKey: ['ad-keyscore', adId],
    queryFn: async () => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.get(ENDPOINTS.ads.keyscore(adId));
      return data;
    },
    select: (payload) =>
      ('data' in (payload as { data?: unknown })
        ? (payload as { data: KeyScorePayload }).data
        : (payload as KeyScorePayload)) ?? { score: 0 },
    enabled: Boolean(adId),
    staleTime: 10 * 60 * 1000,
  });
}
