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
    select: (payload) => ({
      reviews: Array.isArray(payload?.data) ? payload.data : [],
      averageRating: payload?.meta?.average_rating ?? null,
      count:
        payload?.meta?.reviews_count ??
        (Array.isArray(payload?.data) ? payload.data.length : 0),
    }),
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
