import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export interface PriceIndexRow {
  id?: string;
  city: string;
  quarter?: string | null;
  type?: string | null;
  median_price: number;
  ads_count: number;
  currency?: string;
  updated_at?: string;
}

export interface PriceIndexResponse {
  data: PriceIndexRow[];
}

export function usePriceIndex() {
  return useQuery<PriceIndexResponse, Error, PriceIndexRow[]>({
    queryKey: ['price-index'],
    queryFn: async () => {
      const { data } = await apiClient.get<PriceIndexResponse>(
        ENDPOINTS.priceIndex,
        { params: { per_page: 100 } },
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 60 * 60 * 1000,
  });
}
