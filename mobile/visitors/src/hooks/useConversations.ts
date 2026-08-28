import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useRef } from 'react';
import { AppState } from 'react-native';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { mergeDelta, mergeFreshPage, runDeltaSync } from '@/hooks/chat-delta';
import type {
  Conversation,
  Message,
  MessageReaction,
  MessagesResponse,
} from '@/types/conversation';

/**
 * On ne réévalue quasi jamais la première page : le cache (persisté chiffré)
 * reste affiché à l'ouverture instantanée façon WhatsApp Web, et `syncDelta`
 * tire uniquement ce qui est arrivé depuis. 23 h de fraîcheur ⇒ pas de refetch
 * plein au remount tant que l'app vit.
 */
const CHAT_MESSAGES_STALE_MS = 23 * 60 * 60 * 1000;

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

interface ConversationDetailResponse {
  data: Conversation;
}

/**
 * Fiche d'UNE conversation (`GET /conversations/{uuid}`) — nom, avatar,
 * `last_seen_at` de l'interlocuteur, annonce liée. C'est la source de
 * vérité du header du thread : ne JAMAIS dépendre du cache de la liste
 * (vide quand on arrive depuis une annonce ou un deep-link push — le
 * header affichait « Conversation » sans avatar).
 *
 * `placeholderData` sème depuis la liste si elle est déjà en cache →
 * header instantané, puis rafraîchi par le fetch. Le refetch 60 s tient
 * la présence (« En ligne » / « Vu à … ») à jour.
 */
export function useConversationMeta(uuid: string | undefined) {
  const qc = useQueryClient();
  return useQuery<ConversationDetailResponse, Error, Conversation>({
    queryKey: ['conversation-meta', uuid],
    queryFn: async () => {
      if (!uuid) throw new Error('Missing conversation uuid');
      const { data } = await apiClient.get<ConversationDetailResponse>(
        ENDPOINTS.conversations.detail(uuid),
      );
      return data;
    },
    select: (payload) => payload.data,
    enabled: Boolean(uuid),
    placeholderData: () => {
      const list = qc.getQueryData<ConvListResponse>(['conversations']);
      const found = list?.data?.find((c) => c.uuid === uuid);
      return found ? { data: found } : undefined;
    },
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000,
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
 * Single-conversation thread. Sans WebSocket, on poll les messages
 * toutes les 4 secondes tant que l'écran est monté. Quand Reverb est
 * connecté (`realtimeConnected=true`), les events WS sont la source de
 * vérité et le polling passe à 30 s (filet de sécurité anti-drift)
 * au lieu de marteler l'API pour rien.
 */
export function useConversation(uuid: string | undefined, realtimeConnected = false) {
  const qc = useQueryClient();
  const isSyncingRef = useRef(false);
  const bootstrappedRef = useRef<string | null>(null);
  const prevConnectedRef = useRef(false);

  const query = useQuery<MessagesResponse, Error, Message[]>({
    queryKey: ['conversation-messages', uuid],
    queryFn: async () => {
      if (!uuid) throw new Error('Missing conversation uuid');
      const prev = qc.getQueryData<MessagesResponse>(['conversation-messages', uuid]);
      const { data } = await apiClient.get<MessagesResponse>(
        ENDPOINTS.conversations.messages(uuid),
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
    select: (payload) => (Array.isArray(payload?.data) ? payload.data : []),
    enabled: Boolean(uuid),
    // Cache persisté → ouverture instantanée sans refetch plein ; la fraîcheur
    // vient de syncDelta (bootstrap + AppState + reconnexion + delta-poll).
    staleTime: CHAT_MESSAGES_STALE_MS,
    gcTime: 30 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  });
  const refetch = query.refetch;

  const syncDelta = useCallback(async () => {
    if (!uuid || isSyncingRef.current) {
      return;
    }
    isSyncingRef.current = true;
    try {
      await runDeltaSync({
        getMessages: () => {
          const c = qc.getQueryData<MessagesResponse>(['conversation-messages', uuid]);
          return Array.isArray(c?.data) ? c!.data : [];
        },
        applyFresh: (fresh) => {
          qc.setQueryData<MessagesResponse | undefined>(
            ['conversation-messages', uuid],
            (prev) => {
              const list = Array.isArray(prev?.data) ? prev!.data : [];
              return { data: mergeDelta(list, fresh) };
            },
          );
        },
        fetchAfter: async (afterIso) => {
          const { data } = await apiClient.get<MessagesResponse>(
            ENDPOINTS.conversations.messages(uuid),
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
  }, [uuid, qc]);

  // Bootstrap depuis le cache restauré (persisté chiffré) : `isFetchedAfterMount`
  // est faux quand la liste vient du snapshot → c'est là qu'il faut rattraper ce
  // qui est arrivé depuis (ouverture instantanée façon WhatsApp Web).
  useEffect(() => {
    if (!uuid) {
      return;
    }
    const list = query.data;
    if (!list || list.length === 0) {
      return;
    }
    if (bootstrappedRef.current === uuid) {
      return;
    }
    bootstrappedRef.current = uuid;
    if (!query.isFetchedAfterMount) {
      void syncDelta();
    }
  }, [uuid, query.data, query.isFetchedAfterMount, syncDelta]);

  // Retour au premier plan → delta ciblé (jamais un refetch plein).
  useEffect(() => {
    if (!uuid) {
      return;
    }
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        void syncDelta();
      }
    });
    return () => sub.remove();
  }, [uuid, syncDelta]);

  // Reconnexion WS (false→true) → delta + un refetch plein invisible qui
  // réconcilie read_at / suppressions manqués pendant la coupure (le merge
  // préserve l'ordre et ne flashe pas : isLoading reste faux).
  useEffect(() => {
    if (!uuid) {
      return;
    }
    const was = prevConnectedRef.current;
    prevConnectedRef.current = realtimeConnected;
    if (!was && realtimeConnected) {
      void syncDelta();
      void refetch();
    }
  }, [uuid, realtimeConnected, syncDelta, refetch]);

  // Filet de sécurité léger : delta-poll (pas de refetch plein). 4 s sans WS,
  // 30 s quand Reverb tient le fil.
  useEffect(() => {
    if (!uuid) {
      return;
    }
    const period = realtimeConnected ? 30 * 1000 : 4 * 1000;
    const id = setInterval(() => void syncDelta(), period);
    return () => clearInterval(id);
  }, [uuid, realtimeConnected, syncDelta]);

  return query;
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

/** Upload une pièce jointe puis crée le message associé (2 étapes API). */
export function useUploadAttachment(uuid: string | undefined) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (file: { uri: string; name: string; type: string }) => {
      if (!uuid) throw new Error('Missing conversation uuid');
      const form = new FormData();
      form.append('file', file as unknown as Blob);
      const { data: uploadRes } = await apiClient.post<{ data: Record<string, unknown> }>(
        ENDPOINTS.conversations.attachments(uuid),
        form,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      const descriptor = (uploadRes?.data ?? uploadRes) as Record<string, unknown>;
      if (typeof descriptor.url !== 'string' || typeof descriptor.signed_url !== 'string') {
        throw new Error('Réponse pièce jointe invalide.');
      }
      const { data: msgRes } = await apiClient.post<{ data: Message }>(
        ENDPOINTS.conversations.messages(uuid),
        {
          type: descriptor.type ?? 'image',
          attachments: [descriptor],
        },
      );
      return msgRes.data ?? (msgRes as unknown as Message);
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
