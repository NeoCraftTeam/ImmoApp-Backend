import { useMutation } from '@tanstack/react-query';

import { apiClient } from '@/api/client';

export interface RentEstimateInput {
  city_id: string;
  type_id: string;
  surface: number;
  bedrooms?: number;
}

export interface RentEstimateResult {
  estimated_min?: number;
  estimated_median?: number;
  estimated_max?: number;
  price_per_sqm?: { p25: number; p50: number; p75: number };
  sample_count?: number;
  is_unreliable?: boolean;
  surface?: number;
  type_scope_matched?: boolean;
  bedrooms_scope_matched?: boolean;
  error?: string;
}

/**
 * POST `/ads/rent-estimate` — heuristic median-based estimate. Mutation
 * (not query) because each "Estimer" tap is a fresh server-side
 * computation, even with the same inputs (the backend reads live
 * median data and caches it for 1 h). Mutation pattern also gives us
 * a clear `isPending` flag for the submit button spinner.
 */
export function useRentEstimate() {
  return useMutation<RentEstimateResult, Error, RentEstimateInput>({
    mutationFn: async (input) => {
      const { data } = await apiClient.post<RentEstimateResult>(
        '/ads/rent-estimate',
        input,
      );
      return data;
    },
  });
}
