import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  PropertyAttributeCategory,
  PropertyAttributeMeta,
  PropertyAttributesResponse,
} from '@/types/property-attribute';

const QUERY_KEY = ['property-attributes'];
const STALE_TIME = 30 * 60 * 1000;

async function fetchPropertyAttributes(): Promise<PropertyAttributesResponse> {
  const { data } = await apiClient.get<PropertyAttributesResponse>(
    ENDPOINTS.propertyAttributes,
  );
  return data;
}

/**
 * Property attribute metadata — fetched once per session and cached
 * 30 minutes (matches the backend CDN cache). The map keys are the
 * raw attribute slugs returned on `Ad.attributes`.
 */
export function usePropertyAttributes() {
  return useQuery<
    PropertyAttributesResponse,
    Error,
    Record<string, PropertyAttributeMeta>
  >({
    queryKey: QUERY_KEY,
    queryFn: fetchPropertyAttributes,
    select: (payload) => {
      const data = payload?.data;
      return data && typeof data === 'object' && !Array.isArray(data) ? data : {};
    },
    staleTime: STALE_TIME,
  });
}

/**
 * Same endpoint, grouped by category — powers the equipment accordions
 * in the search filter sheet. Shares the query cache with
 * `usePropertyAttributes` (only the `select` differs).
 */
export function usePropertyAttributeGroups() {
  return useQuery<
    PropertyAttributesResponse,
    Error,
    PropertyAttributeCategory[]
  >({
    queryKey: QUERY_KEY,
    queryFn: fetchPropertyAttributes,
    select: (payload) => (Array.isArray(payload?.grouped) ? payload.grouped : []),
    staleTime: STALE_TIME,
  });
}
