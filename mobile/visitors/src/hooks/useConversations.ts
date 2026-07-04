import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type {
  Conversation,
  Message,
  MessageReaction,
  MessagesResponse,
} from '@/types/conversation';

interface ConvListResponse {
  data: Conversation[];
}

export function useConversations() {
  return useQuery<ConvListResponse, Error, Conversation[]>({
    queryKey: ['conversations'],
    queryFn: async () => {
      const { data } = await apiClient.get<ConvListResponse>(
        ENDPOINTS.conversations.list,
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    staleTime: 15 * 1000,
    refetchInterval: 30 * 1000,
  });
}

interface StartConversationResponse {
  data: Conversation;
}

/**
 * Démarre (ou récupère) la conversation avec l'annonceur d'une annonce.
 * Le backend applique un `findOrCreate` sur `ad_id` : renvoie 200 si le
 * thread existait déjà, 201 sinon. Retourne l'uuid pour naviguer vers le
 * fil. Un 403 signifie que l'annonce doit d'abord être débloquée.
 */
export function useStartConversation() {
  const qc = useQueryClient();
  return useMutation<Conversation, Error, string>({
    mutationFn: async (adId: string) => {
      const { data } = await apiClient.post<StartConversationResponse>(
        ENDPOINTS.conversations.create,
        { ad_id: adId },
      );
      return data.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['conversations'] });
    },
  });
}

/**
 * Single-conversation thread. We poll the messages endpoint every
 * 4 seconds while the screen is mounted — Expo Go doesn't expose
 * Pusher/Echo natively, so polling is the lowest-friction substitute
 * for real-time. Cheap (the backend caches per-page) and the user
 * never has to pull-to-refresh.
 */
export function useConversation(uuid: string | undefined) {
  return useQuery<MessagesResponse, Error, Message[]>({
    queryKey: ['conversation-messages', uuid],
    queryFn: async () => {
      if (!uuid) throw new Error('Missing conversation uuid');
      const { data } = await apiClient.get<MessagesResponse>(
        ENDPOINTS.conversations.messages(uuid),
      );
      return data;
    },
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    enabled: Boolean(uuid),
    staleTime: 2 * 1000,
    refetchInterval: 4 * 1000,
  });
}

/**
 * Send a text message. Optimistically inserts the message in the
 * conversation cache (avec un uuid local `temp:*`) avant la résolution
 * réseau ; le serveur renvoie ensuite le vrai message, on remplace.
 * Failed mutations laissent le brouillon dans le thread avec un flag
 * `is_failed=true` pour que l'UI offre un "Réessayer".
 */
export function useSendMessage(uuid: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (body: string) => {
      if (!uuid) throw new Error('Missing conversation uuid');
      const { data } = await apiClient.post<{ data: Message }>(
        ENDPOINTS.conversations.messages(uuid),
        { body },
      );
      return data.data ?? (data as unknown as Message);
    },
    onMutate: async (body) => {
      if (!uuid) return { tempId: null };
      await qc.cancelQueries({ queryKey: ['conversation-messages', uuid] });
      const tempId = `temp:${Date.now()}`;
      const optimistic: Message = {
        uuid: tempId,
        conversation_uuid: uuid,
        body,
        sender_id: '__me__', // remplacé par le vrai sender_id côté UI via useMe
        created_at: new Date().toISOString(),
        is_optimistic: true,
      };
      qc.setQueryData<{ data: Message[] } | undefined>(
        ['conversation-messages', uuid],
        (prev) => {
          const list = Array.isArray(prev?.data) ? prev!.data : [];
          return { data: [...list, optimistic] };
        },
      );
      return { tempId };
    },
    onError: (_err, _vars, ctx) => {
      if (!uuid || !ctx?.tempId) return;
      qc.setQueryData<{ data: Message[] } | undefined>(
        ['conversation-messages', uuid],
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
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['conversation-messages', uuid] });
      qc.invalidateQueries({ queryKey: ['conversations'] });
    },
  });
}

export function useMarkConversationRead(uuid: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      if (!uuid) throw new Error('Missing conversation uuid');
      await apiClient.patch(ENDPOINTS.conversations.read(uuid));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['conversations'] });
    },
  });
}

/** Upload une pièce jointe (image ou doc) à un thread. */
export function useUploadAttachment(uuid: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (file: { uri: string; name: string; type: string }) => {
      if (!uuid) throw new Error('Missing conversation uuid');
      const form = new FormData();
      form.append('file', file as unknown as Blob);
      const { data } = await apiClient.post(
        ENDPOINTS.conversations.attachments(uuid),
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      return data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['conversation-messages', uuid] });
      qc.invalidateQueries({ queryKey: ['conversations'] });
    },
  });
}

/** "X est en train d'écrire…" — ping rate-limité côté backend. */
export function useSetTyping(uuid: string | undefined) {
  return useMutation({
    mutationFn: async () => {
      if (!uuid) return;
      await apiClient.post(ENDPOINTS.conversations.typing(uuid)).catch(() => {
        /* swallow rate-limit & network blips — typing est best-effort */
      });
    },
  });
}

/** Toggle réaction emoji. Optimistic + invalidate. */
export function useToggleReaction(conversationUuid: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (input: { messageUuid: string; emoji: string; reacted: boolean }) => {
      if (input.reacted) {
        await apiClient.delete(ENDPOINTS.messages.reactions(input.messageUuid), {
          data: { emoji: input.emoji },
        });
      } else {
        await apiClient.post(ENDPOINTS.messages.reactions(input.messageUuid), {
          emoji: input.emoji,
        });
      }
    },
    onMutate: async ({ messageUuid, emoji, reacted }) => {
      if (!conversationUuid) return;
      await qc.cancelQueries({ queryKey: ['conversation-messages', conversationUuid] });
      qc.setQueryData<{ data: Message[] } | undefined>(
        ['conversation-messages', conversationUuid],
        (prev) => {
          if (!prev || !Array.isArray(prev.data)) return prev;
          return {
            data: prev.data.map((m) => {
              if (m.uuid !== messageUuid) return m;
              const list = Array.isArray(m.reactions) ? [...m.reactions] : [];
              const idx = list.findIndex((r) => r.emoji === emoji);
              if (reacted) {
                if (idx >= 0) {
                  const r = list[idx]!;
                  const nextCount = Math.max(0, (r.count ?? 1) - 1);
                  if (nextCount === 0) list.splice(idx, 1);
                  else list[idx] = { ...r, count: nextCount, reacted_by_me: false };
                }
              } else {
                if (idx >= 0) {
                  const r = list[idx]!;
                  list[idx] = {
                    ...r,
                    count: (r.count ?? 0) + 1,
                    reacted_by_me: true,
                  };
                } else {
                  list.push({ emoji, count: 1, reacted_by_me: true } satisfies MessageReaction);
                }
              }
              return { ...m, reactions: list };
            }),
          };
        },
      );
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['conversation-messages', conversationUuid] });
    },
  });
}

export function useDeleteMessage(conversationUuid: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (messageUuid: string) => {
      await apiClient.delete(ENDPOINTS.messages.delete(messageUuid));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['conversation-messages', conversationUuid] });
      qc.invalidateQueries({ queryKey: ['conversations'] });
    },
  });
}
