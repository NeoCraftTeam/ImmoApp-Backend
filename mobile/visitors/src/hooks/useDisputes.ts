import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  Dispute,
  DisputeListResponse,
  EvidenceType,
} from '@/types/dispute';

export function useDisputes(openOnly = false) {
  return useQuery<DisputeListResponse, Error, Dispute[]>({
    queryKey: ['disputes', openOnly],
    queryFn: async () => {
      const { data } = await apiClient.get<DisputeListResponse>(
        ENDPOINTS.disputes.list,
        { params: { open_only: openOnly } },
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 30 * 1000,
  });
}

export function useDispute(id: string | undefined) {
  return useQuery<{ data: Dispute } | Dispute, Error, Dispute>({
    queryKey: ['dispute', id],
    queryFn: async () => {
      if (!id) throw new Error('Missing dispute id');
      const { data } = await apiClient.get(ENDPOINTS.disputes.detail(id));
      return data;
    },
    select: (payload) =>
      ('data' in (payload as { data?: unknown })
        ? (payload as { data: Dispute }).data
        : (payload as Dispute)),
    enabled: Boolean(id),
    refetchInterval: 30 * 1000,
  });
}

export function useCreateDispute() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: {
      subject: string;
      description: string;
      payment_id?: string;
      ad_id?: string;
    }) => {
      const { data } = await apiClient.post(ENDPOINTS.disputes.create, input);
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['disputes'] }),
  });
}

export function useSendDisputeMessage(id: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (body: string) => {
      if (!id) throw new Error('Missing dispute id');
      const { data } = await apiClient.post(ENDPOINTS.disputes.messages(id), {
        body,
      });
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['dispute', id] }),
  });
}

export function useUploadDisputeEvidence(id: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { file: { uri: string; name: string; type: string }; type: EvidenceType }) => {
      if (!id) throw new Error('Missing dispute id');
      const form = new FormData();
      form.append('file', input.file as unknown as Blob);
      form.append('type', input.type);
      const { data } = await apiClient.post(
        ENDPOINTS.disputes.evidence(id),
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      return data;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['dispute', id] }),
  });
}
