import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

/**
 * Profile of the currently-authenticated user. Backend `/me` returns the
 * full UserResource; the visitor app only reads the identity fields, so
 * we type a narrow projection here. Add fields as needed when the
 * account screen grows (avatar, currency preference, etc.).
 */
export interface MeResponse {
  data: {
    id: string;
    firstname: string;
    lastname: string;
    email: string;
    is_verified?: boolean;
  };
}

export function useMe(enabled = true) {
  return useQuery<MeResponse, Error, MeResponse['data']>({
    queryKey: ['me'],
    queryFn: async () => {
      const { data } = await apiClient.get<MeResponse>(ENDPOINTS.auth.me);
      return data;
    },
    select: (payload) => payload.data,
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}
