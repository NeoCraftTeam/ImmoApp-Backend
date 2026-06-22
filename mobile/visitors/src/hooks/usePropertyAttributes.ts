import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  PropertyAttributeMeta,
  PropertyAttributesResponse,
} from '@/types/property-attribute';

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
    queryKey: ['property-attributes'],
    queryFn: async () => {
      const { data } = await apiClient.get<PropertyAttributesResponse>(
        ENDPOINTS.propertyAttributes,
      );
      return data;
    },
    select: (payload) => {
      const out: Record<string, PropertyAttributeMeta> = {};
      const list = Array.isArray(payload?.data) ? payload.data : [];
      for (const attr of list) {
        if (attr?.key) {
          out[attr.key] = attr;
        }
      }
      return out;
    },
    staleTime: 30 * 60 * 1000,
  });
}
