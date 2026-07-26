import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { Review, ReviewListResponse } from '@/types/review';

interface ReviewsPayload {
  reviews: Review[];
  averageRating: number | null;
  count: number;
}

export function useReviews(adId: string | undefined) {
  return useQuery<ReviewListResponse, Error, ReviewsPayload>({
    queryKey: ['ad-reviews', adId],
    queryFn: async () => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.get<ReviewListResponse>(
        ENDPOINTS.ads.reviews(adId),
      );
      return data;
    },
    select: (payload): ReviewsPayload => {
      const reviews = Array.isArray(payload?.data) ? payload.data : [];
      return {
        reviews,
        averageRating: payload?.meta?.average_rating ?? null,
        count: payload?.meta?.reviews_count ?? reviews.length,
      };
    },
    enabled: Boolean(adId),
    staleTime: 60 * 1000,
  });
}

export function useCreateReview(adId: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { rating: number; comment?: string }) => {
      if (!adId) throw new Error('Missing ad id');
      const { data } = await apiClient.post(ENDPOINTS.reviews.create, {
        ad_id: adId,
        rating: input.rating,
        comment: input.comment ?? null,
      });
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ad-reviews', adId] });
      qc.invalidateQueries({ queryKey: ['ad', adId] });
    },
  });
}
