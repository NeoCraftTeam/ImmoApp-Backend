import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad } from '@/types/ad';

interface NearbyResponse {
  data: Ad[];
}

export function useNearbyAds(input: {
  latitude?: number;
  longitude?: number;
  radiusKm?: number;
  enabled?: boolean;
}) {
  const enabled =
    (input.enabled ?? true) &&
    input.latitude != null &&
    input.longitude != null;

  return useQuery<NearbyResponse, Error, Ad[]>({
    queryKey: ['ads-nearby', input.latitude, input.longitude, input.radiusKm ?? 5],
    queryFn: async () => {
      const { data } = await apiClient.get<NearbyResponse>(ENDPOINTS.ads.nearby, {
        params: {
          latitude: input.latitude,
          longitude: input.longitude,
          radius: input.radiusKm ?? 5,
        },
      });
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}
