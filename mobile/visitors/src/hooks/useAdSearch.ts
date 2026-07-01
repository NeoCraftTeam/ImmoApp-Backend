import { useInfiniteQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad, AdFeedResponse } from '@/types/ad';
import {
  EMPTY_FILTERS,
  activeFilterCount,
  filtersToParams,
  type AdFilters,
} from '@/types/filters';

/**
 * Search backed by `/ads/search` (dedicated search endpoint —
 * Meilisearch with a SQL fallback). Sends `q` as a free-text query —
 * the backend matches on title, description, and quarter / city names —
 * plus an optional `AdFilters` payload that narrows by price / surface /
 * transaction type.
 *
 * NB: `/ads` (the plain index) ignores `q` entirely, which is why the
 * search tab previously returned unfiltered results from everywhere.
 *
 * The hook stays enabled as long as either the text query OR any
 * filter is active, so a user can browse "all under 200k FCFA" with
 * no text query and still get results.
 */
export function useAdSearch(query: string, filters: AdFilters = EMPTY_FILTERS) {
  const trimmed = query.trim();
  const hasFilters = activeFilterCount(filters) > 0;
  const hasQuery = trimmed.length >= 2;

  return useInfiniteQuery<
    AdFeedResponse,
    Error,
    Ad[],
    readonly unknown[],
    number
  >({
    queryKey: ['ad-search', trimmed, filters] as const,
    queryFn: async ({ pageParam }) => {
      const params: Record<string, string | number> = {
        per_page: 15,
        page: pageParam,
        ...filtersToParams(filters),
      };
      if (hasQuery) params.q = trimmed;
      const { data } = await apiClient.get<AdFeedResponse>(ENDPOINTS.ads.search, {
        params,
      });
      return data;
    },
    initialPageParam: 1,
    getNextPageParam: (last) => {
      const meta = last.meta as undefined | { current_page?: number; last_page?: number };
      if (meta?.current_page == null || meta.last_page == null) return undefined;
      return meta.current_page < meta.last_page
        ? meta.current_page + 1
        : undefined;
    },
    select: (data) =>
      Array.isArray(data?.pages)
        ? data.pages.flatMap((p) => (Array.isArray(p?.data) ? p.data : []))
        : [],
    enabled: hasQuery || hasFilters,
    staleTime: 60 * 1000,
  });
}
