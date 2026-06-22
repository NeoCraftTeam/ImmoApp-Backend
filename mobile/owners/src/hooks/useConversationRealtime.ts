import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { subscribePrivate } from '@/services/echo';
import type { ConversationMessage } from '@/types/conversation';

/**
 * Branche le thread sur Reverb pour 4 events :
 * - `message.sent` → ajoute le message dans le cache
 * - `messages.read` → bascule `read_at` côté UI
 * - `message.deleted` → retire le message
 * - `user.typing` → callback exposé pour afficher un indicateur
 *
 * `onTyping` est typé en props plutôt que côté event listener brut pour
 * laisser le screen gérer le state local. Cleanup auto via useEffect.
 */
export function useConversationRealtime(
  conversationId: string | undefined,
  onTyping?: (userId: string) => void,
): void {
  const qc = useQueryClient();

  useEffect(() => {
    if (!conversationId) return;

    return subscribePrivate(
      `conversation.${conversationId}`,
      ['message.sent', 'messages.read', 'message.deleted', 'user.typing'],
      (event, raw) => {
        const data = raw as { message?: ConversationMessage; user_id?: string };
        if (event === 'message.sent' && data?.message) {
          qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
            ['owner-conversation-messages', conversationId],
            (prev) => {
              const list = Array.isArray(prev?.data) ? prev!.data : [];
              if (list.some((m) => m.id === data.message!.id)) return prev;
              return { data: [...list, data.message!] };
            },
          );
          qc.invalidateQueries({ queryKey: ['owner-conversations'] });
        }
        if (event === 'messages.read') {
          qc.invalidateQueries({ queryKey: ['owner-conversation-messages', conversationId] });
        }
        if (event === 'message.deleted' && data?.message) {
          qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
            ['owner-conversation-messages', conversationId],
            (prev) => {
              if (!prev) return prev;
              const list = Array.isArray(prev.data) ? prev.data : [];
              return { data: list.filter((m) => m.id !== data.message!.id) };
            },
          );
        }
        if (event === 'user.typing' && data?.user_id && onTyping) {
          onTyping(data.user_id);
        }
      },
    );
  }, [conversationId, onTyping, qc]);
}
