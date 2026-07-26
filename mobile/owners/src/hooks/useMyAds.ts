import { useInfiniteQuery, useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad, PaginatedAds } from '@/types/ad';

export interface MyAdsFilters {
  q?: string;
  status?: string;
  type_id?: string;
  city_id?: string;
}

/**
 * Paginated list of the owner's ads — GET /my/ads. Supports search +
 * status/type/city filters. Uses cursor/offset pagination via the
 * `meta.current_page` returned by the backend paginator.
 */
export function useMyAds(filters: MyAdsFilters = {}, enabled = true) {
  return useInfiniteQuery<PaginatedAds, Error>({
    queryKey: ['my-ads', filters],
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      const { data } = await apiClient.get<PaginatedAds>(ENDPOINTS.my.ads, {
        params: {
          page: pageParam,
          per_page: 15,
          q: filters.q || undefined,
          status: filters.status || undefined,
          type_id: filters.type_id || undefined,
          city_id: filters.city_id || undefined,
        },
      });
      return data;
    },
    getNextPageParam: (last) => {
      const current = last.meta?.current_page ?? 1;
      const lastPage = last.meta?.last_page ?? current;
      return current < lastPage ? current + 1 : undefined;
    },
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}

/**
 * Lightweight one-shot fetch of draft ads only — used by the dashboard +
 * the "Brouillons" pinned section without spinning up infinite query
 * pagination.
 */
export function useDraftAds(enabled = true) {
  return useQuery<PaginatedAds, Error, Ad[]>({
    queryKey: ['my-ads', 'drafts'],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedAds>(ENDPOINTS.my.ads, {
        params: { status: 'draft', per_page: 20 },
      });
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}
