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

/** GET /conversations/{id}/messages — thread complet (polling adaptatif). */
export function useConversation(id: string | undefined, realtimeConnected = false) {
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
    refetchInterval: realtimeConnected ? 30 * 1000 : 4 * 1000,
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
        uuid: tempId,
        conversation_uuid: id,
        sender_id: '__me__',
        body,
        created_at: new Date().toISOString(),
        client_id: tempId,
        is_optimistic: true,
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
    onError: (_err, _body, ctx) => {
      if (!id || !ctx?.tempId) return;
      qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
        ['owner-conversation-messages', id],
        (prev) => {
          if (!prev) return prev;
          const list = Array.isArray(prev.data) ? prev.data : [];
          return {
            data: list.map((m) =>
              m.uuid === ctx.tempId ? { ...m, is_failed: true, is_optimistic: false } : m,
            ),
          };
        },
      );
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

/**
 * Ajoute ou retire une réaction emoji sur un message.
 * POST/DELETE /messages/{uuid}/reactions {emoji}. Invalide le fil.
 */
export function useToggleReaction(conversationId: string | undefined) {
  const qc = useQueryClient();
  return useMutation<
    void,
    Error,
    { messageId: string; emoji: string; reacted: boolean }
  >({
    mutationFn: async ({ messageId, emoji, reacted }) => {
      if (reacted) {
        await apiClient.delete(ENDPOINTS.chat.reaction(messageId), {
          data: { emoji },
        });
      } else {
        await apiClient.post(ENDPOINTS.chat.reaction(messageId), { emoji });
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['owner-conversation-messages', conversationId] });
    },
  });
}

/**
 * Suppression d'un message (le backend limite à l'expéditeur, < 24 h).
 * Retrait optimiste immédiat du cache pour un retour instantané.
 */
export function useDeleteMessage(conversationId: string | undefined) {
  const qc = useQueryClient();
  return useMutation<void, Error, string, { previous?: { data: ConversationMessage[] } }>({
    mutationFn: async (messageId) => {
      await apiClient.delete(ENDPOINTS.chat.deleteMessage(messageId));
    },
    onMutate: async (messageId) => {
      if (!conversationId) return {};
      await qc.cancelQueries({ queryKey: ['owner-conversation-messages', conversationId] });
      const previous = qc.getQueryData<{ data: ConversationMessage[] }>([
        'owner-conversation-messages',
        conversationId,
      ]);
      qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
        ['owner-conversation-messages', conversationId],
        (prev) => {
          if (!prev || !Array.isArray(prev.data)) return prev;
          return { data: prev.data.filter((m) => m.uuid !== messageId) };
        },
      );
      return { previous };
    },
    onError: (_err, _messageId, ctx) => {
      if (conversationId && ctx?.previous) {
        qc.setQueryData(['owner-conversation-messages', conversationId], ctx.previous);
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['owner-conversation-messages', conversationId] });
      qc.invalidateQueries({ queryKey: ['owner-conversations'] });
    },
  });
}

/**
 * Envoi d'une pièce jointe (photo) — upload multipart puis création du
 * message avec le descripteur renvoyé (l'upload seul ne crée pas de
 * message côté backend).
 */
export function useUploadAttachment(id: string | undefined) {
  const qc = useQueryClient();
  return useMutation<ConversationMessage, Error, { uri: string; name: string; type: string }>({
    mutationFn: async (file) => {
      if (!id) throw new Error('Missing conversation id');
      const form = new FormData();
      form.append('file', file as unknown as Blob);
      const { data: uploadRes } = await apiClient.post<{ data: Record<string, unknown> }>(
        ENDPOINTS.chat.attachments(id),
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      const descriptor = (uploadRes?.data ?? uploadRes) as Record<string, unknown>;
      if (typeof descriptor.url !== 'string' || typeof descriptor.signed_url !== 'string') {
        throw new Error('Réponse pièce jointe invalide.');
      }
      const { data: msgRes } = await apiClient.post<{ data: ConversationMessage }>(
        ENDPOINTS.chat.sendMessage(id),
        {
          type: descriptor.type ?? 'image',
          attachments: [descriptor],
        },
      );
      return msgRes.data ?? (msgRes as unknown as ConversationMessage);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['owner-conversation-messages', id] });
      qc.invalidateQueries({ queryKey: ['owner-conversations'] });
    },
  });
}
