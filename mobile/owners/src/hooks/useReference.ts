import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  AdTypeOption,
  CityOption,
  PropertyAttribute,
  QuarterOption,
} from '@/types/owner';

/** Cities — cached 10 min. */
export function useCities() {
  return useQuery<{ data: CityOption[] }, Error, CityOption[]>({
    queryKey: ['cities'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: CityOption[] }>(ENDPOINTS.ref.cities, {
        params: { per_page: 200 },
      });
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    staleTime: 10 * 60 * 1000,
  });
}

/** Quarters for a given city — cached 10 min, keyed by cityId. */
export function useQuarters(cityId: string | undefined) {
  return useQuery<{ data: QuarterOption[] }, Error, QuarterOption[]>({
    queryKey: ['quarters', cityId],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: QuarterOption[] }>(ENDPOINTS.ref.quarters, {
        params: { city_id: cityId, per_page: 300 },
      });
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled: !!cityId,
    staleTime: 10 * 60 * 1000,
  });
}

/** Ad / property types — cached 30 min. */
export function useAdTypes() {
  return useQuery<{ data: AdTypeOption[] }, Error, AdTypeOption[]>({
    queryKey: ['ad-types'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AdTypeOption[] }>(ENDPOINTS.ref.adTypes);
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    staleTime: 30 * 60 * 1000,
  });
}

/** Equipment / property attributes — cached 30 min. */
export function usePropertyAttributes() {
  return useQuery<{ data: PropertyAttribute[] }, Error, PropertyAttribute[]>({
    queryKey: ['property-attributes'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: PropertyAttribute[] }>(
        ENDPOINTS.ref.propertyAttributes,
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    staleTime: 30 * 60 * 1000,
  });
}

/** POST /geo/city — create a missing city on the fly. */
export function useCreateCity() {
  const qc = useQueryClient();
  return useMutation<CityOption, Error, { name: string }>({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<{ data?: CityOption } | CityOption>(
        ENDPOINTS.ref.geoCity,
        input,
      );
      return (data as { data?: CityOption }).data ?? (data as CityOption);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['cities'] }),
  });
}

/** POST /geo/quarter — create a missing quarter on the fly. */
export function useCreateQuarter() {
  const qc = useQueryClient();
  return useMutation<QuarterOption, Error, { name: string; city_id: string }>({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<{ data?: QuarterOption } | QuarterOption>(
        ENDPOINTS.ref.geoQuarter,
        input,
      );
      return (data as { data?: QuarterOption }).data ?? (data as QuarterOption);
    },
    onSuccess: (_d, vars) =>
      qc.invalidateQueries({ queryKey: ['quarters', vars.city_id] }),
  });
}
