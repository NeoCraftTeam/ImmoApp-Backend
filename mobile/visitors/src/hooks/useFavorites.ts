import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import type { Ad } from '@/types/ad';

/**
 * Backend `/my/favorites` returns a paginated `{ data: Ad[] }` payload
 * via `AdResource::collection(...)` (capped at 15 per page). For the
 * visitor app we only show the first page — power-users with hundreds
 * of favorites are atypical and don't justify infinite scroll on this
 * screen yet.
 */
interface FavoritesResponse {
  data: Ad[];
}

export function useFavorites(enabled = true) {
  return useQuery<FavoritesResponse, Error, Ad[]>({
    queryKey: ['my-favorites'],
    queryFn: async () => {
      const { data } = await apiClient.get<FavoritesResponse>('/my/favorites');
      return data;
    },
    select: (payload) => payload.data,
    enabled,
    staleTime: 60 * 1000,
  });
}
