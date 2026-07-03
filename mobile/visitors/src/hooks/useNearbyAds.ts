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

  const radiusKm = input.radiusKm ?? 5;

  return useQuery<NearbyResponse, Error, Ad[]>({
    queryKey: ['ads-nearby', input.latitude, input.longitude, radiusKm],
    queryFn: async () => {
      const { data } = await apiClient.get<NearbyResponse>(ENDPOINTS.ads.nearby, {
        params: {
          latitude: input.latitude,
          longitude: input.longitude,
          // ⚠️ Le backend compare en MÈTRES (ST_DistanceSphere) — envoyer
          // le rayon en km (5) donnait un rayon de 5 m → liste vide.
          radius: radiusKm * 1000,
        },
      });
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    enabled,
    staleTime: 2 * 60 * 1000,
  });
}
