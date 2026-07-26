import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { BailleurFollowState, BailleurProfile } from '@/types/bailleur';

export function useBailleur(username: string | undefined) {
  return useQuery<{ data: BailleurProfile } | BailleurProfile, Error, BailleurProfile>({
    queryKey: ['bailleur', username],
    queryFn: async () => {
      if (!username) throw new Error('Missing username');
      const { data } = await apiClient.get(
        ENDPOINTS.users.publicProfile(username),
      );
      return data;
    },
    select: (payload) =>
      ('data' in (payload as { data?: unknown })
        ? (payload as { data: BailleurProfile }).data
        : (payload as BailleurProfile)),
    enabled: Boolean(username),
    staleTime: 5 * 60 * 1000,
  });
}

export function useBailleurFollow(username: string | undefined) {
  const qc = useQueryClient();

  const query = useQuery<BailleurFollowState, Error>({
    queryKey: ['bailleur-follow', username],
    queryFn: async () => {
      if (!username) throw new Error('Missing username');
      const { data } = await apiClient.get<BailleurFollowState>(
        ENDPOINTS.bailleurs.follow(username),
      );
      return data;
    },
    enabled: Boolean(username),
    staleTime: 60 * 1000,
  });

  const mutation = useMutation({
    mutationFn: async () => {
      if (!username) throw new Error('Missing username');
      const { data } = await apiClient.post<BailleurFollowState>(
        ENDPOINTS.bailleurs.follow(username),
      );
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bailleur-follow', username] });
      qc.invalidateQueries({ queryKey: ['bailleur', username] });
    },
  });

  return { ...query, toggle: mutation.mutate, isToggling: mutation.isPending };
}
