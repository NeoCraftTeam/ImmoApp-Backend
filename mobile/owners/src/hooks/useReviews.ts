import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Review } from '@/types/owner';

interface ReviewsResponse {
  data?: Review[];
  meta?: { average_rating?: number | null; reviews_count?: number };
}

/**
 * GET /ads/{adId}/reviews — paginated reviews for a single ad. Le backend
 * expose les reviews **par annonce**, pas en agrégat owner ; le screen
 * owner consomme cette source par ad, puis somme côté client (count +
 * moyenne pondérée).
 */
export function useAdReviews(adId: string | undefined, enabled = true) {
  return useQuery({
    queryKey: ['ad-reviews', adId],
    queryFn: async () => {
      if (!adId) {
        return { data: [], meta: {} } satisfies ReviewsResponse;
      }
      const { data } = await apiClient.get<ReviewsResponse>(
        ENDPOINTS.reviews.forAd(adId),
        { params: { per_page: 30 } },
      );
      return data;
    },
    select: (payload) => {
      const reviews = Array.isArray(payload?.data) ? payload.data : [];
      // L'index backend ne renvoie pas d'agrégat dans `meta` (pagination
      // standard) → on calcule la moyenne côté client à partir des avis
      // chargés (per_page=30, exact pour la plupart des annonces).
      const rated = reviews.filter((r) => typeof r.rating === 'number');
      const clientAverage =
        rated.length > 0
          ? rated.reduce((sum, r) => sum + r.rating, 0) / rated.length
          : null;
      return {
        reviews,
        averageRating: payload?.meta?.average_rating ?? clientAverage,
        count: payload?.meta?.reviews_count ?? reviews.length,
      };
    },
    enabled: enabled && !!adId,
    staleTime: 60 * 1000,
  });
}

/**
 * POST /reviews/{reviewId}/respond — réponse publique du bailleur à un
 * avis. Invalide les reviews de l'annonce concernée pour reseaux.
 */
export function useRespondReview(adId?: string) {
  const qc = useQueryClient();
  return useMutation<void, Error, { reviewId: string; response: string }>({
    mutationFn: async ({ reviewId, response }) => {
      await apiClient.post(ENDPOINTS.reviews.respond(reviewId), { response });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ad-reviews', adId] });
    },
  });
}
