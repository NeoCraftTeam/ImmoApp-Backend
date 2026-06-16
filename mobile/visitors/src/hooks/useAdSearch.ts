import { useInfiniteQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad, AdFeedResponse } from '@/types/ad';

/**
 * Lightweight text search backed by `/ads` (the offset paginator).
 * Sends `q` as a free-text query — the backend matches on title,
 * description, and quarter / city names.
 *
 * Disabled until `query` has at least 2 chars so a single-character
 * keypress doesn't fire a backend request. Once enabled, debouncing
 * is left to the calling screen (which uses `useDebounce` from a
 * lightweight hook below).
 */
export function useAdSearch(query: string, enabled: boolean) {
  return useInfiniteQuery<
    AdFeedResponse,
    Error,
    Ad[],
    readonly unknown[],
    number
  >({
    queryKey: ['ad-search', query] as const,
    queryFn: async ({ pageParam }) => {
      const { data } = await apiClient.get<AdFeedResponse>(ENDPOINTS.ads.list, {
        params: { q: query, per_page: 15, page: pageParam },
      });
      return data;
    },
    initialPageParam: 1,
    getNextPageParam: (last, allPages) => {
      const meta = last.meta as undefined | { current_page?: number; last_page?: number };
      if (meta?.current_page == null || meta.last_page == null) return undefined;
      return meta.current_page < meta.last_page
        ? meta.current_page + 1
        : undefined;
    },
    select: (data) => data.pages.flatMap((p) => p.data),
    enabled: enabled && query.trim().length >= 2,
    staleTime: 60 * 1000,
  });
}
