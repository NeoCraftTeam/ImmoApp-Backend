import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';

import { subscribePrivate } from '@/services/echo';
import type { Message } from '@/types/conversation';

/**
 * S'abonne en temps réel à `private-conversation.{uuid}` via Laravel
 * Reverb / Echo (Pusher protocol) et synchronise le cache TanStack
 * Query au passage. Si la config Reverb n'est pas dispo (env vars
 * vides), le hook no-op et le polling 4 s du `useConversation` reste
 * la seule source de vérité.
 *
 * Events captés :
 *   - `message.sent` → ajout au cache
 *   - `messages.read` → met à jour `read_at` sur les messages concernés
 *   - `message.deleted` → retire du cache
 *   - `message.reaction.added` / `.removed` → patch reactions[]
 *   - `user.typing` → expose `typingUser` pour l'UI "X écrit…"
 */
export interface RealtimeState {
  isConnected: boolean;
  typingUser: { user_id: string; is_typing: boolean } | null;
}

interface MessageSentPayload {
  message?: Message;
  [key: string]: unknown;
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

export function useConversationRealtime(
  conversationUuid: string | undefined,
  currentUserId: string | undefined,
): RealtimeState {
  const qc = useQueryClient();
  const [state, setState] = useState<RealtimeState>({
    isConnected: false,
    typingUser: null,
  });
  const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!conversationUuid) return;

    const unsubscribe = subscribePrivate(
      `conversation.${conversationUuid}`,
      [
        'message.sent',
        'messages.read',
        'message.deleted',
        'message.reaction.added',
        'message.reaction.removed',
        'user.typing',
      ],
      (event, data) => {
        if (event === 'message.sent') {
          const payload = data as MessageSentPayload;
          const message = payload.message ?? (payload as unknown as Message);
          if (!message?.uuid || message.sender_id === currentUserId) return;
          qc.setQueryData<{ data: Message[] } | undefined>(
            ['conversation-messages', conversationUuid],
            (prev) => {
              const list = Array.isArray(prev?.data) ? prev!.data : [];
              if (list.some((m) => m.uuid === message.uuid)) return prev;
              return { data: [...list, message] };
            },
          );
          qc.invalidateQueries({ queryKey: ['conversations'] });
        } else if (event === 'messages.read') {
          const payload = data as MessageReadPayload;
          qc.setQueryData<{ data: Message[] } | undefined>(
            ['conversation-messages', conversationUuid],
            (prev) => {
              if (!prev || !Array.isArray(prev.data)) return prev;
              return {
                data: prev.data.map((m) =>
                  m.sender_id === currentUserId
                    ? { ...m, read_at: m.read_at ?? payload.read_at }
                    : m,
                ),
              };
            },
          );
        } else if (event === 'message.deleted') {
          const payload = data as { message_uuid: string };
          qc.setQueryData<{ data: Message[] } | undefined>(
            ['conversation-messages', conversationUuid],
            (prev) => {
              if (!prev || !Array.isArray(prev.data)) return prev;
              return { data: prev.data.filter((m) => m.uuid !== payload.message_uuid) };
            },
          );
        } else if (event === 'message.reaction.added') {
          const p = data as ReactionPayload;
          patchReaction(qc, conversationUuid, p, 'add', currentUserId);
        } else if (event === 'message.reaction.removed') {
          const p = data as ReactionPayload;
          patchReaction(qc, conversationUuid, p, 'remove', currentUserId);
        } else if (event === 'user.typing') {
          const p = data as TypingPayload;
          if (p.user_id === currentUserId) return;
          setState((s) => ({ ...s, typingUser: p }));
          if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
          typingTimeoutRef.current = setTimeout(() => {
            setState((s) => ({ ...s, typingUser: null }));
          }, 4000);
        }
      },
    );

    setState((s) => ({ ...s, isConnected: true }));
    return () => {
      unsubscribe();
      setState((s) => ({ ...s, isConnected: false, typingUser: null }));
      if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
    };
  }, [conversationUuid, currentUserId, qc]);

  return state;
}

function patchReaction(
  qc: ReturnType<typeof useQueryClient>,
  conversationUuid: string,
  payload: ReactionPayload,
  mode: 'add' | 'remove',
  currentUserId: string | undefined,
): void {
  qc.setQueryData<{ data: Message[] } | undefined>(
    ['conversation-messages', conversationUuid],
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
              list[idx] = {
                ...r,
                count: (r.count ?? 0) + 1,
                reacted_by_me: isMe ? true : r.reacted_by_me,
              };
            } else {
              list.push({ emoji: payload.emoji, count: 1, reacted_by_me: isMe });
            }
          } else if (idx >= 0) {
            const r = list[idx]!;
            const next = Math.max(0, (r.count ?? 1) - 1);
            if (next === 0) list.splice(idx, 1);
            else {
              list[idx] = {
                ...r,
                count: next,
                reacted_by_me: isMe ? false : r.reacted_by_me,
              };
            }
          }
          return { ...m, reactions: list };
        }),
      };
    },
  );
}
