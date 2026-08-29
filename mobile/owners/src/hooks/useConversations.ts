import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useRef } from 'react';
import { AppState } from 'react-native';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { mergeDelta, mergeFreshPage, runDeltaSync } from '@/hooks/chat-delta';
import type {
  ConversationMessage,
  ConversationPreview,
} from '@/types/conversation';

/**
 * On ne réévalue quasi jamais la première page : le cache (persisté chiffré)
 * reste affiché à l'ouverture instantanée façon WhatsApp Web, et `syncDelta`
 * tire uniquement ce qui est arrivé depuis. 23 h de fraîcheur ⇒ pas de refetch
 * plein au remount tant que l'app vit.
 */
const CHAT_MESSAGES_STALE_MS = 23 * 60 * 60 * 1000;

interface ConversationsResponse {
  data?: ConversationPreview[];
}

interface MessagesResponse {
  data?: ConversationMessage[];
  /** Curseur de pagination historique (non utilisé côté mobile — pas de load-more). */
  next_cursor?: string | null;
  /** `true` s'il reste des messages au-delà de ce lot (delta `?after`). */
  has_more?: boolean;
}

interface ConversationDetailResponse {
  data?: ConversationPreview;
}

/**
 * Fiche d'UNE conversation (`GET /conversations/{id}`) — nom, avatar,
 * `last_seen_at` de l'interlocuteur, annonce liée. Source de vérité du
 * header du thread : ne pas dépendre du cache de la liste (vide en
 * arrivant par deep-link push → header sans nom ni présence).
 * `placeholderData` sème depuis la liste si disponible ; le refetch
 * 60 s tient la présence (« En ligne » / « Vu à … ») à jour.
 */
export function useConversationMeta(id: string | undefined) {
  const qc = useQueryClient();
  return useQuery<ConversationDetailResponse, Error, ConversationPreview | undefined>({
    queryKey: ['owner-conversation-meta', id],
    queryFn: async () => {
      if (!id) throw new Error('Missing conversation id');
      const { data } = await apiClient.get<ConversationDetailResponse>(
        ENDPOINTS.chat.conversation(id),
      );
      return data;
    },
    select: (p) => p?.data,
    enabled: Boolean(id),
    placeholderData: () => {
      const list = qc.getQueryData<ConversationsResponse>(['owner-conversations']);
      const found = list?.data?.find((c) => c.uuid === id);
      return found ? { data: found } : undefined;
    },
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000,
  });
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

/**
 * Thread d'UNE conversation. Le cache persisté (chiffré) reste affiché à
 * l'ouverture (instantané façon WhatsApp Web) ; `syncDelta` ne tire QUE les
 * messages arrivés depuis (`?after=<created_at UTC>`), jamais un refetch plein.
 * Quand Reverb tient le fil, le delta-poll passe en filet 30 s ; sans WS, 4 s.
 */
export function useConversation(id: string | undefined, realtimeConnected = false) {
  const qc = useQueryClient();
  const isSyncingRef = useRef(false);
  const bootstrappedRef = useRef<string | null>(null);
  const prevConnectedRef = useRef(false);

  const query = useQuery<MessagesResponse, Error, ConversationMessage[]>({
    queryKey: ['owner-conversation-messages', id],
    queryFn: async () => {
      if (!id) throw new Error('Missing conversation id');
      const prev = qc.getQueryData<MessagesResponse>(['owner-conversation-messages', id]);
      const { data } = await apiClient.get<MessagesResponse>(
        ENDPOINTS.chat.messages(id),
      );
      // Backend renvoie DESCENDANT (newest-first) ; on remet en ASCENDANT
      // (oldest→newest) pour coller à l'append temps-réel / optimiste / delta et
      // au scroll-to-end (cf. web useChatMessages).
      const freshPageAscending = Array.isArray(data?.data)
        ? [...data.data].reverse()
        : [];
      const prevList = Array.isArray(prev?.data) ? prev!.data : [];
      return { data: mergeFreshPage(prevList, freshPageAscending) };
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled: !!id,
    // Cache persisté → ouverture instantanée sans refetch plein ; la fraîcheur
    // vient de syncDelta (bootstrap + AppState + reconnexion + delta-poll).
    staleTime: CHAT_MESSAGES_STALE_MS,
    gcTime: 30 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  });
  const refetch = query.refetch;

  const syncDelta = useCallback(async () => {
    if (!id || isSyncingRef.current) {
      return;
    }
    isSyncingRef.current = true;
    try {
      await runDeltaSync({
        getMessages: () => {
          const c = qc.getQueryData<MessagesResponse>(['owner-conversation-messages', id]);
          return Array.isArray(c?.data) ? c!.data : [];
        },
        applyFresh: (fresh) => {
          qc.setQueryData<MessagesResponse | undefined>(
            ['owner-conversation-messages', id],
            (prev) => {
              const list = Array.isArray(prev?.data) ? prev!.data : [];
              return { data: mergeDelta(list, fresh) };
            },
          );
        },
        fetchAfter: async (afterIso) => {
          const { data } = await apiClient.get<MessagesResponse>(
            ENDPOINTS.chat.messages(id),
            { params: { after: afterIso } },
          );
          return {
            data: Array.isArray(data?.data) ? data.data : [],
            has_more: Boolean(data?.has_more),
          };
        },
      });
    } finally {
      isSyncingRef.current = false;
    }
  }, [id, qc]);

  // Bootstrap depuis le cache restauré (persisté chiffré) : `isFetchedAfterMount`
  // est faux quand la liste vient du snapshot → rattraper ce qui est arrivé depuis
  // (ouverture instantanée façon WhatsApp Web).
  useEffect(() => {
    if (!id) {
      return;
    }
    const list = query.data;
    if (!list || list.length === 0) {
      return;
    }
    if (bootstrappedRef.current === id) {
      return;
    }
    bootstrappedRef.current = id;
    if (!query.isFetchedAfterMount) {
      void syncDelta();
    }
  }, [id, query.data, query.isFetchedAfterMount, syncDelta]);

  // Retour au premier plan → delta ciblé (jamais un refetch plein).
  useEffect(() => {
    if (!id) {
      return;
    }
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        void syncDelta();
      }
    });
    return () => sub.remove();
  }, [id, syncDelta]);

  // Reconnexion WS (false→true) → delta + un refetch plein invisible qui
  // réconcilie read_at / suppressions manqués pendant la coupure (le merge
  // préserve l'ordre et ne flashe pas : isLoading reste faux).
  useEffect(() => {
    if (!id) {
      return;
    }
    const was = prevConnectedRef.current;
    prevConnectedRef.current = realtimeConnected;
    if (!was && realtimeConnected) {
      void syncDelta();
      void refetch();
    }
  }, [id, realtimeConnected, syncDelta, refetch]);

  // Filet de sécurité léger : delta-poll (pas de refetch plein). 4 s sans WS,
  // 30 s quand Reverb tient le fil.
  useEffect(() => {
    if (!id) {
      return;
    }
    const period = realtimeConnected ? 30 * 1000 : 4 * 1000;
    const timer = setInterval(() => void syncDelta(), period);
    return () => clearInterval(timer);
  }, [id, realtimeConnected, syncDelta]);

  return query;
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
