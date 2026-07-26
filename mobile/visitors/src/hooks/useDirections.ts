import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export type DirectionsProfile = 'driving-car' | 'foot-walking' | 'cycling-regular';

export interface DirectionsSummary {
  distance_m: number;
  duration_s: number;
  profile: DirectionsProfile;
  cached?: boolean;
}

export interface DirectionsResponse {
  data: {
    summary: DirectionsSummary;
    geometry?: GeoJSON.LineString | null;
  };
}

/**
 * Backend-proxied OpenRouteService directions. Cached 1 h per route
 * pair on the server side; the client cache mirrors that with a 5 min
 * stale window so the user can flick between transport profiles
 * without re-roundtripping the backend.
 */
export function useDirections(input: {
  fromLat?: number;
  fromLng?: number;
  toLat?: number;
  toLng?: number;
  profile?: DirectionsProfile;
  enabled?: boolean;
}) {
  const enabled =
    (input.enabled ?? true) &&
    input.fromLat != null &&
    input.fromLng != null &&
    input.toLat != null &&
    input.toLng != null;

  return useQuery<DirectionsResponse, Error>({
    queryKey: [
      'directions',
      input.fromLat,
      input.fromLng,
      input.toLat,
      input.toLng,
      input.profile ?? 'driving-car',
    ],
    queryFn: async () => {
      const { data } = await apiClient.get<DirectionsResponse>(
        ENDPOINTS.geo.directions,
        {
          params: {
            from_lat: input.fromLat,
            from_lng: input.fromLng,
            to_lat: input.toLat,
            to_lng: input.toLng,
            profile: input.profile ?? 'driving-car',
          },
        },
      );
      return data;
    },
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}
