import { useMutation, useQueryClient, type InfiniteData } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import type { Ad, AdFeedResponse } from '@/types/ad';

interface ToggleResponse {
  is_favorited: boolean;
  message: string;
}

/**
 * POST `/ads/{ad}/favorite` — toggles the favorite state for the auth'd
 * user. Optimistically flips `is_favorited` on every cached entry of
 * the ad we can find (feed pages, search pages, single-ad query, the
 * `/my/favorites` list) so the heart tap feels instant; rolls back on
 * error.
 *
 * The backend is authoritative — `onSettled` invalidates the favorites
 * list so the screen shows the freshly added/removed entry the next
 * time it's visible. Feed/search keep the optimistic flag (no
 * re-invalidate) because invalidating them on every heart tap would
 * cancel the user's scroll position.
 */
export function useToggleFavorite() {
  const qc = useQueryClient();

  return useMutation<ToggleResponse, Error, { adId: string }, { previous: Ad[] | undefined }>({
    mutationFn: async ({ adId }) => {
      const { data } = await apiClient.post<ToggleResponse>(`/ads/${adId}/favorite`);
      return data;
    },

    onMutate: async ({ adId }) => {
      // 1. Cancel any in-flight queries that read this ad so they don't
      //    overwrite our optimistic state when they resolve.
      await qc.cancelQueries({ queryKey: ['ad-feed'] });
      await qc.cancelQueries({ queryKey: ['ad-search'] });
      await qc.cancelQueries({ queryKey: ['ad', adId] });
      await qc.cancelQueries({ queryKey: ['my-favorites'] });

      const previous = qc.getQueryData<Ad[]>(['my-favorites']);

      // 2. Flip is_favorited on every cached page of the feed + search.
      const flipPages = (data: InfiniteData<AdFeedResponse> | undefined) => {
        if (!data) return data;
        return {
          ...data,
          pages: data.pages.map((p) => ({
            ...p,
            data: p.data.map((ad) =>
              ad.id === adId ? { ...ad, is_favorited: !ad.is_favorited } : ad,
            ),
          })),
        };
      };
      qc.setQueriesData<InfiniteData<AdFeedResponse>>({ queryKey: ['ad-feed'] }, flipPages);
      qc.setQueriesData<InfiniteData<AdFeedResponse>>({ queryKey: ['ad-search'] }, flipPages);

      // 3. Flip on the single-ad detail cache, if loaded.
      qc.setQueryData<{ data: Ad }>(['ad', adId], (current) =>
        current
          ? { data: { ...current.data, is_favorited: !current.data.is_favorited } }
          : current,
      );

      // 4. Optimistically remove from /my/favorites if it was favorited;
      //    we leave addition to the onSettled invalidate so the new
      //    entry appears with all its server-side metadata (rating,
      //    review count, etc.) rather than a partial optimistic stub.
      qc.setQueryData<Ad[]>(['my-favorites'], (current) =>
        current ? current.filter((ad) => ad.id !== adId) : current,
      );

      return { previous };
    },

    onError: (_err, { adId }, context) => {
      // Roll back every cache we touched. Re-fetching is the safest
      // recovery — the optimistic flip is no longer trustworthy.
      qc.invalidateQueries({ queryKey: ['ad-feed'] });
      qc.invalidateQueries({ queryKey: ['ad-search'] });
      qc.invalidateQueries({ queryKey: ['ad', adId] });
      if (context?.previous) {
        qc.setQueryData(['my-favorites'], context.previous);
      } else {
        qc.invalidateQueries({ queryKey: ['my-favorites'] });
      }
    },

    onSettled: () => {
      // Authoritative refresh of the favorites list — covers the
      // "added" case our optimistic update deliberately skipped.
      qc.invalidateQueries({ queryKey: ['my-favorites'] });
    },
  });
}
