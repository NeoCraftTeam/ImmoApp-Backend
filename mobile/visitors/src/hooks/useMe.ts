import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { queryKeys } from '@/lib/query-keys';
import type { AuthUser } from '@/types/user';

/**
 * Profile of the currently-authenticated user. Backend `/me` returns
 * the full UserResource; the type lives in `@/types/user` and grows
 * alongside the account screens that consume it (profile editor,
 * settings, credit balance, etc.).
 */
export interface MeResponse {
  data: AuthUser;
}

export function useMe(enabled = true) {
  return useQuery<MeResponse, Error, AuthUser>({
    queryKey: queryKeys.me(),
    queryFn: async () => {
      const { data } = await apiClient.get<MeResponse>(ENDPOINTS.auth.me);
      return data;
    },
    select: (payload) => payload.data,
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}
