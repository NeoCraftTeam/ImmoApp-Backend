import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { FacetsResponse } from '@/types/facets';

/**
 * Catalogue facet counts — same `['facets']` query as the web
 * (`useSearchStaticData`): fetched once, cached 5 minutes, never
 * re-keyed on filter changes.
 */
export function useAdFacets() {
  return useQuery<{ success?: boolean; data?: FacetsResponse }, Error, FacetsResponse>({
    queryKey: ['facets'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ success?: boolean; data?: FacetsResponse }>(
        ENDPOINTS.ads.facets,
      );
      return data;
    },
    select: (payload) => payload?.data ?? {},
    staleTime: 5 * 60 * 1000,
  });
}
