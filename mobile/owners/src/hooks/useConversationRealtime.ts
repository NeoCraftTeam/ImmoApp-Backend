import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';

import { isEchoConfigured, subscribePrivate } from '@/services/echo';
import type { ConversationMessage } from '@/types/conversation';

/**
 * S'abonne en temps réel à `private-conversation.{id}` via Laravel Reverb
 * (Pusher protocol) et synchronise le cache TanStack Query. Sans config
 * Reverb (env vars vides), no-op — le polling 4 s de `useConversation`
 * reste la seule source de vérité.
 *
 * Signature `(id, currentUserId)` : on passe l'id de l'utilisateur (string
 * STABLE) plutôt qu'un callback — un callback inline non mémoïsé faisait
 * ré-souscrire le canal Reverb à chaque frappe (dep instable de l'effet).
 * L'état « en train d'écrire » est géré ici et exposé via `typingUser`.
 *
 * Events captés : message.sent (ajout), messages.read (patch read_at),
 * message.deleted (retrait), reaction.added/removed (patch), user.typing.
 */
export interface RealtimeState {
  isConnected: boolean;
  typingUser: { user_id: string; is_typing: boolean } | null;
}

interface MessageReadPayload {
  reader_id: string;
  read_at: string;
}

interface ReactionPayload {
  message_uuid: string;
  user_id: string;
  emoji: string;
}

interface TypingPayload {
  user_id: string;
  is_typing: boolean;
}

const MESSAGES_KEY = (id: string) => ['owner-conversation-messages', id] as const;

export function useConversationRealtime(
  conversationId: string | undefined,
  currentUserId: string | undefined,
): RealtimeState {
  const qc = useQueryClient();
  const [state, setState] = useState<RealtimeState>({
    isConnected: false,
    typingUser: null,
  });
  const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!conversationId) return;

    const unsubscribe = subscribePrivate(
      `conversation.${conversationId}`,
      [
        'message.sent',
        'messages.read',
        'message.deleted',
        'message.reaction.added',
        'message.reaction.removed',
        'user.typing',
      ],
      (event, raw) => {
        const data = raw as {
          message?: ConversationMessage;
          message_uuid?: string;
          uuid?: string;
        } & MessageReadPayload &
          ReactionPayload &
          TypingPayload;

        if (event === 'message.sent') {
          const msg = (data?.message ?? (raw as ConversationMessage)) as ConversationMessage;
          // On ignore l'écho de son propre message (déjà en optimiste).
          if (!msg?.uuid || msg.sender_id === currentUserId) return;
          qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
            MESSAGES_KEY(conversationId),
            (prev) => {
              const list = Array.isArray(prev?.data) ? prev!.data : [];
              if (list.some((m) => m.uuid === msg.uuid)) return prev;
              return { data: [...list, msg] };
            },
          );
          qc.invalidateQueries({ queryKey: ['owner-conversations'] });
        } else if (event === 'messages.read') {
          // Patch local : marque MES messages comme lus (pas de refetch).
          qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
            MESSAGES_KEY(conversationId),
            (prev) => {
              if (!prev || !Array.isArray(prev.data)) return prev;
              return {
                data: prev.data.map((m) =>
                  m.sender_id === currentUserId
                    ? { ...m, read_at: m.read_at ?? data.read_at }
                    : m,
                ),
              };
            },
          );
        } else if (event === 'message.deleted') {
          const deletedUuid = data?.message_uuid ?? data?.message?.uuid ?? data?.uuid;
          if (!deletedUuid) return;
          qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
            MESSAGES_KEY(conversationId),
            (prev) => {
              if (!prev || !Array.isArray(prev.data)) return prev;
              return { data: prev.data.filter((m) => m.uuid !== deletedUuid) };
            },
          );
        } else if (
          event === 'message.reaction.added' ||
          event === 'message.reaction.removed'
        ) {
          if (!data?.message_uuid) return;
          patchReaction(
            qc,
            conversationId,
            data,
            event === 'message.reaction.added' ? 'add' : 'remove',
            currentUserId,
          );
        } else if (event === 'user.typing') {
          if (!data?.user_id || data.user_id === currentUserId) return;
          setState((s) => ({ ...s, typingUser: { user_id: data.user_id, is_typing: true } }));
          if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
          typingTimeoutRef.current = setTimeout(() => {
            setState((s) => ({ ...s, typingUser: null }));
          }, 4000);
        }
      },
    );

    // `isConnected` ne reflète que le cas où un canal WS a été souscrit.
    setState((s) => ({ ...s, isConnected: isEchoConfigured() }));
    return () => {
      unsubscribe();
      setState((s) => ({ ...s, isConnected: false, typingUser: null }));
      if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
    };
  }, [conversationId, currentUserId, qc]);

  return state;
}

function patchReaction(
  qc: ReturnType<typeof useQueryClient>,
  conversationId: string,
  payload: ReactionPayload,
  mode: 'add' | 'remove',
  currentUserId: string | undefined,
): void {
  qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
    MESSAGES_KEY(conversationId),
    (prev) => {
      if (!prev || !Array.isArray(prev.data)) return prev;
      const isMe = payload.user_id === currentUserId;
      return {
        data: prev.data.map((m) => {
          if (m.uuid !== payload.message_uuid) return m;
          const list = Array.isArray(m.reactions) ? [...m.reactions] : [];
          const idx = list.findIndex((r) => r.emoji === payload.emoji);
          if (mode === 'add') {
            if (idx >= 0) {
              const r = list[idx]!;
              const userIds = Array.isArray(r.user_ids) ? r.user_ids : [];
              list[idx] = {
                ...r,
                count: (r.count ?? userIds.length) + 1,
                user_ids: isMe ? [...userIds, payload.user_id] : userIds,
              };
            } else {
              list.push({
                emoji: payload.emoji,
                count: 1,
                user_ids: isMe ? [payload.user_id] : [],
              });
            }
          } else if (idx >= 0) {
            const r = list[idx]!;
            const next = Math.max(0, (r.count ?? 1) - 1);
            const userIds = (Array.isArray(r.user_ids) ? r.user_ids : []).filter(
              (uid) => uid !== payload.user_id,
            );
            if (next === 0) list.splice(idx, 1);
            else list[idx] = { ...r, count: next, user_ids: userIds };
          }
          return { ...m, reactions: list };
        }),
      };
    },
  );
}
