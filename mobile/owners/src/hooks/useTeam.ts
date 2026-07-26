import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { TeamMember, TeamMemberRole } from '@/types/team';

interface TeamResponse {
  data?: TeamMember[];
}

/** GET /my/team. */
export function useTeam(enabled = true) {
  return useQuery<TeamResponse, Error, TeamMember[]>({
    queryKey: ['team'],
    queryFn: async () => {
      const { data } = await apiClient.get<TeamResponse>(ENDPOINTS.team.list);
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 60 * 1000,
  });
}

export function useInviteTeamMember() {
  const qc = useQueryClient();
  return useMutation<
    TeamMember,
    Error,
    { email: string; role: TeamMemberRole; firstname?: string; lastname?: string }
  >({
    mutationFn: async (payload) => {
      const { data } = await apiClient.post<{ data: TeamMember }>(
        ENDPOINTS.team.invite,
        payload,
      );
      return data.data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['team'] }),
  });
}

export function useRemoveTeamMember() {
  const qc = useQueryClient();
  return useMutation<void, Error, string>({
    mutationFn: async (id) => {
      await apiClient.delete(ENDPOINTS.team.member(id));
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['team'] }),
  });
}
