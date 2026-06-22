import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Ad } from '@/types/ad';

interface RecommendationsResponse {
  data: Ad[];
  meta?: {
    source?: 'personalized' | 'trending' | string;
    algorithm?: string;
  };
}

/**
 * Personalized ad recommendations — backend pondère type/ville/budget
 * /fraîcheur/popularité quand l'utilisateur est authentifié, et tombe
 * sur un mix trending + boosted en cold-start pour les visiteurs
 * anonymes. Cache 10 min (côté backend `cdn.cache:600`).
 */
export function useRecommendations(perPage = 8) {
  return useQuery<RecommendationsResponse, Error, Ad[]>({
    queryKey: ['recommendations', perPage],
    queryFn: async () => {
      const { data } = await apiClient.get<RecommendationsResponse>(
        ENDPOINTS.ads.recommendations,
        { params: { per_page: perPage } },
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 10 * 60 * 1000,
  });
}
