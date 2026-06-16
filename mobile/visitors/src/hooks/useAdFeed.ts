import { useInfiniteQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad, AdFeedResponse } from '@/types/ad';

/**
 * Paginated ad-feed hook backed by the backend's cursor paginator.
 * Returns the canonical TanStack-Query `useInfiniteQuery` shape so the
 * UI can drive `fetchNextPage()` on scroll-near-end.
 *
 * Stale-time tuned to match the backend's CDN cache (`cdn.cache:300`
 * on the `/ads/feed` route) — re-fetching within 5 min would hit the
 * same response anyway, so React Query keeps the cached copy and
 * skips the network call.
 */
export function useAdFeed(perPage = 15) {
  return useInfiniteQuery<AdFeedResponse, Error, Ad[], readonly unknown[], string | null>({
    queryKey: ['ad-feed', perPage] as const,
    queryFn: async ({ pageParam }) => {
      const params: Record<string, string | number> = { per_page: perPage };
      if (pageParam) {
        params.cursor = pageParam;
      }
      const { data } = await apiClient.get<AdFeedResponse>(ENDPOINTS.ads.feed, { params });
      return data;
    },
    initialPageParam: null,
    getNextPageParam: (last) => last.meta?.next_cursor ?? null,
    // Flatten the page collection to a single `Ad[]` for FlatList consumption.
    select: (data) => data.pages.flatMap((p) => p.data),
    staleTime: 5 * 60 * 1000,
  });
}
