import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';

export interface CityOption {
  id: string;
  name: string;
}

export interface AdTypeOption {
  id: string;
  name: string;
}

/**
 * Tiny helpers for the estimator's dropdowns. We don't ship a full
 * autocomplete on mobile yet — the city list is small enough (~50
 * West-African capitals + districts) that loading them up-front and
 * picking from a scrollable sheet is faster than a debounced fetch.
 */
export function useCitiesList() {
  return useQuery<{ data: CityOption[] }, Error, CityOption[]>({
    queryKey: ['cities-all'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CityOption[] }>('/cities', {
        params: { per_page: 200 },
      });
      return data;
    },
    select: (payload) => payload.data,
    staleTime: 10 * 60 * 1000,
  });
}

export function useAdTypes() {
  return useQuery<{ data: AdTypeOption[] }, Error, AdTypeOption[]>({
    queryKey: ['ad-types'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AdTypeOption[] }>('/ad-types');
      return data;
    },
    select: (payload) => payload.data,
    staleTime: 30 * 60 * 1000,
  });
}
