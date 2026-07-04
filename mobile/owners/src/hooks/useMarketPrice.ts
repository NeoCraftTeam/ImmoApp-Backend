import { useMutation } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { MarketPriceEstimate } from '@/types/marketprice';

interface EstimatePayload {
  city_id?: string;
  surface?: number;
  bedrooms?: number;
  ad_type_id?: string;
}

/** Réponse brute de RentEstimatorController@estimate. */
interface RentEstimateResponse {
  estimated_min: number;
  estimated_median: number;
  estimated_max: number;
  sample_count?: number;
  currency?: string;
  bedrooms_scope_matched?: boolean;
}

/**
 * GET /rent-estimate — estimateur de loyer marché (percentiles p25/p50/p75
 * × surface). Le backend attend `city_id`, `type_id` et `surface` en query
 * (bedrooms optionnel). On mappe la réponse (min/median/max + sample_count)
 * vers la forme MarketPriceEstimate lue par l'écran.
 */
export function useMarketEstimate() {
  return useMutation<MarketPriceEstimate, Error, EstimatePayload>({
    mutationFn: async (payload) => {
      const { data } = await apiClient.get<RentEstimateResponse>(
        ENDPOINTS.market.estimate,
        {
          params: {
            city_id: payload.city_id,
            type_id: payload.ad_type_id,
            surface: payload.surface,
            bedrooms: payload.bedrooms,
          },
        },
      );
      return {
        estimated_price: data.estimated_median,
        currency: data.currency,
        range: { low: data.estimated_min, high: data.estimated_max },
        comparable_count: data.sample_count,
        is_unreliable:
          data.bedrooms_scope_matched === false || (data.sample_count ?? 0) < 3,
      };
    },
  });
}
