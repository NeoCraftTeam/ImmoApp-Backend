import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { AuthUser } from '@/types/user';

export interface MeResponse {
  data: AuthUser;
}

/**
 * Profile of the currently-authenticated owner. Backend `/auth/me`
 * returns the full UserResource; the type lives in `@/types/user`.
 */
export function useMe(enabled = true) {
  return useQuery<MeResponse, Error, AuthUser>({
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
