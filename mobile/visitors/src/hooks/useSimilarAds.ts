import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad } from '@/types/ad';

interface SimilarResponse {
  data: Ad[];
}

/**
 * Similar ads — Meilisearch-backed on the server (same type + city +
 * price ±30%). Cached 5 minutes — recommendations don't shift fast
 * enough to merit shorter staleness, and refetching on every detail
 * navigation would hammer the CDN cache.
 */
export function useSimilarAds(adId: string | undefined) {
  return useQuery<SimilarResponse, Error, Ad[]>({
    queryKey: ['ad-similar', adId],
    queryFn: async () => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.get<SimilarResponse>(
        ENDPOINTS.ads.similar(adId),
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    enabled: Boolean(adId),
    staleTime: 5 * 60 * 1000,
  });
}
