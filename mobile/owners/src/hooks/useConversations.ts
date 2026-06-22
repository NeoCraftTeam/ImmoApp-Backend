import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  ConversationMessage,
  ConversationPreview,
} from '@/types/conversation';

interface ConversationsResponse {
  data?: ConversationPreview[];
}

interface MessagesResponse {
  data?: ConversationMessage[];
}

/** GET /conversations — liste des conversations du bailleur (avec preview). */
export function useConversations(enabled = true) {
  return useQuery<ConversationsResponse, Error, ConversationPreview[]>({
    queryKey: ['owner-conversations'],
    queryFn: async () => {
      const { data } = await apiClient.get<ConversationsResponse>(
        ENDPOINTS.chat.conversations,
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled,
    staleTime: 15 * 1000,
    refetchInterval: 30 * 1000,
  });
}

/** GET /conversations/{id}/messages — thread complet (avec polling 4s). */
export function useConversation(id: string | undefined) {
  return useQuery<MessagesResponse, Error, ConversationMessage[]>({
    queryKey: ['owner-conversation-messages', id],
    queryFn: async () => {
      if (!id) throw new Error('Missing conversation id');
      const { data } = await apiClient.get<MessagesResponse>(
        ENDPOINTS.chat.messages(id),
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled: !!id,
    staleTime: 2 * 1000,
    refetchInterval: 4 * 1000,
  });
}

/** POST /conversations/{id}/messages — envoyer un message (optimistic). */
export function useSendMessage(id: string | undefined) {
  const qc = useQueryClient();
  return useMutation<ConversationMessage, Error, string, { tempId: string | null }>({
    mutationFn: async (body) => {
      if (!id) throw new Error('Missing conversation id');
      const { data } = await apiClient.post<{ data: ConversationMessage }>(
        ENDPOINTS.chat.sendMessage(id),
        { body },
      );
      return data.data ?? (data as unknown as ConversationMessage);
    },
    onMutate: async (body) => {
      if (!id) return { tempId: null };
      await qc.cancelQueries({ queryKey: ['owner-conversation-messages', id] });
      const tempId = `temp:${Date.now()}`;
      const optimistic: ConversationMessage = {
        id: tempId,
        conversation_id: id,
        sender_id: '__me__',
        body,
        created_at: new Date().toISOString(),
        client_id: tempId,
      };
      qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
        ['owner-conversation-messages', id],
        (prev) => {
          const list = Array.isArray(prev?.data) ? prev!.data : [];
          return { data: [...list, optimistic] };
        },
      );
      return { tempId };
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['owner-conversation-messages', id] });
      qc.invalidateQueries({ queryKey: ['owner-conversations'] });
    },
  });
}

export function useMarkConversationRead(id: string | undefined) {
  const qc = useQueryClient();
  return useMutation<void, Error, void>({
    mutationFn: async () => {
      if (!id) return;
      await apiClient.patch(ENDPOINTS.chat.markRead(id));
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['owner-conversations'] }),
  });
}

export function useSetTyping(id: string | undefined) {
  return useMutation<void, Error, void>({
    mutationFn: async () => {
      if (!id) return;
      await apiClient.post(ENDPOINTS.chat.typing(id)).catch(() => {
        /* best-effort */
      });
    },
  });
}
