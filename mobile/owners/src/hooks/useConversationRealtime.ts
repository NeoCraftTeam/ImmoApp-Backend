import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { isEchoConfigured, subscribePrivate } from '@/services/echo';
import type { ConversationMessage } from '@/types/conversation';

export interface RealtimeState {
  isConnected: boolean;
}

/**
 * Branche le thread sur Reverb et synchronise le cache TanStack Query.
 * Sans config Reverb, no-op — le polling du hook `useConversation` reste
 * la source de vérité.
 */
export function useConversationRealtime(
  conversationId: string | undefined,
  onTyping?: (userId: string) => void,
): RealtimeState {
  const qc = useQueryClient();
  const [state, setState] = useState<RealtimeState>({ isConnected: false });

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
          uuid?: string;
          message_uuid?: string;
          user_id?: string;
          emoji?: string;
        };
        if (event === 'message.sent') {
          const msg = (data?.message ?? (data as ConversationMessage)) as ConversationMessage;
          if (!msg?.uuid) return;
          qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
            ['owner-conversation-messages', conversationId],
            (prev) => {
              const list = Array.isArray(prev?.data) ? prev!.data : [];
              if (list.some((m) => m.uuid === msg.uuid)) return prev;
              return { data: [...list, msg] };
            },
          );
          qc.invalidateQueries({ queryKey: ['owner-conversations'] });
        }
        if (event === 'messages.read') {
          qc.invalidateQueries({ queryKey: ['owner-conversation-messages', conversationId] });
        }
        if (event === 'message.deleted') {
          const deletedUuid = data?.message_uuid ?? data?.message?.uuid ?? data?.uuid;
          if (!deletedUuid) return;
          qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
            ['owner-conversation-messages', conversationId],
            (prev) => {
              if (!prev) return prev;
              const list = Array.isArray(prev.data) ? prev.data : [];
              return { data: list.filter((m) => m.uuid !== deletedUuid) };
            },
          );
        }
        if (
          (event === 'message.reaction.added' || event === 'message.reaction.removed')
          && data?.message_uuid
        ) {
          qc.invalidateQueries({ queryKey: ['owner-conversation-messages', conversationId] });
        }
        if (event === 'user.typing' && data?.user_id && onTyping) {
          onTyping(data.user_id);
        }
      },
    );

    setState({ isConnected: isEchoConfigured() });
    return () => {
      unsubscribe();
      setState({ isConnected: false });
    };
  }, [conversationId, onTyping, qc]);

  return state;
}
