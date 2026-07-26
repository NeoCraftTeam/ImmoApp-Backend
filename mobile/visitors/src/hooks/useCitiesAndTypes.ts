import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';

export interface CityOption {
  id: string;
  name: string;
}

export interface AdTypeOption {
  id: string;
  name: string;
  desc?: string | null;
}

/**
 * Tiny helpers for the estimator's dropdowns + the home filter chips.
 * We don't ship a full autocomplete on mobile yet — the city list is
 * small enough (~50 West-African capitals + districts) that loading
 * them up-front and picking from a scrollable sheet is faster than a
 * debounced fetch.
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
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 10 * 60 * 1000,
  });
}

/**
 * Debounce-friendly city autocomplete for the search bar — mirrors the
 * web's `['cities', q]` query (`/cities?q=…&per_page=20`, 5 min cache).
 * Pass the already-debounced input; the query stays off until the
 * first character.
 */
export function useCityAutocomplete(q: string) {
  const trimmed = q.trim();
  return useQuery<{ data: CityOption[] }, Error, CityOption[]>({
    queryKey: ['cities', trimmed],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CityOption[] }>('/cities', {
        params: { q: trimmed, per_page: 20 },
      });
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    enabled: trimmed.length >= 1,
    staleTime: 5 * 60 * 1000,
  });
}

export function useAdTypes() {
  return useQuery<{ data: AdTypeOption[] }, Error, AdTypeOption[]>({
    queryKey: ['ad-types'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AdTypeOption[] }>('/ad-types');
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 30 * 60 * 1000,
  });
}
