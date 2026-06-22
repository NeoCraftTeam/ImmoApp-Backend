import { useMutation } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { MarketPriceEstimate } from '@/types/marketprice';

interface EstimatePayload {
  city_id?: string;
  quarter_id?: string;
  surface?: number;
  bedrooms?: number;
  ad_type_id?: string;
  transaction_type?: 'rent' | 'sale';
}

/** POST /market/estimate — estimateur de prix marché pour bailleur. */
export function useMarketEstimate() {
  return useMutation<MarketPriceEstimate, Error, EstimatePayload>({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ data: MarketPriceEstimate } | MarketPriceEstimate>(
        ENDPOINTS.market.estimate,
        payload,
      );
      return (
        (data as { data?: MarketPriceEstimate }).data ??
        (data as MarketPriceEstimate)
      );
    },
  });
}
