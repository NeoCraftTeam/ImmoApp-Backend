import { useQueryClient } from '@tanstack/react-query';
import { usePathname, useRouter } from 'expo-router';
import { useEffect, useRef } from 'react';

import { subscribePrivate } from '@/services/echo';
import { showToast } from '@/services/toast';
import type { Conversation } from '@/types/conversation';

interface MessageReceivedPayload {
  uuid?: string;
  conversation_uuid?: string;
  sender_id?: string;
  sender?: { id: string; name: string; avatar?: string | null } | null;
  type?: string;
  body?: string | null;
  is_client_sealed?: boolean;
  created_at?: string;
}

interface ConvListResponse {
  data: Conversation[];
}

/**
 * Notifications chat en temps réel HORS fil ouvert : abonne le canal privé
 * `user.{id}` (event `message.received`, diffusé par le backend à chaque
 * nouveau message) et :
 *
 *   1. met à jour le cache de la boîte de réception (preview, non-lus,
 *      remontée en tête) — sans attendre le polling 30 s ;
 *   2. affiche un toast in-app avec action « Voir » → ouvre le fil, sauf
 *      si ce fil est déjà à l'écran ;
 *   3. invalide la liste si la conversation est toute neuve (absente du
 *      cache) pour récupérer sa fiche complète.
 *
 * Le fil ouvert reste géré par `useConversationRealtime` (canal
 * `conversation.{uuid}`) — ce hook ne touche jamais le cache des
 * messages, donc aucun double comptage entre les deux.
 */
export function useChatNotificationsRealtime(userId: string | undefined): void {
  const qc = useQueryClient();
  const router = useRouter();
  const pathname = usePathname();
  const pathnameRef = useRef(pathname);
  const routerRef = useRef(router);
  const seenRef = useRef<Set<string>>(new Set());

  useEffect(() => {
    pathnameRef.current = pathname;
  }, [pathname]);

  useEffect(() => {
    routerRef.current = router;
  }, [router]);

  useEffect(() => {
    if (!userId) return;

    const unsubscribe = subscribePrivate(
      `user.${userId}`,
      ['message.received'],
      (_event, raw) => {
        const data = raw as MessageReceivedPayload;
        const convUuid = data?.conversation_uuid;
        if (!data?.uuid || !convUuid) return;

        // Dédup défensive (retry réseau) — les incréments ne sont pas
        // idempotents.
        const seen = seenRef.current;
        if (seen.has(data.uuid)) return;
        seen.add(data.uuid);
        if (seen.size > 200) {
          const oldest = seen.values().next().value;
          if (oldest !== undefined) seen.delete(oldest);
        }

        const threadPath = `/messages/${convUuid}`;
        const threadOpen = pathnameRef.current === threadPath;

        // 1. Inbox : patch du cache si la conversation y est, sinon refetch
        //    (conversation toute neuve — il faut sa fiche complète).
        const cached = qc.getQueryData<ConvListResponse>(['conversations']);
        const exists = cached?.data?.some((c) => c.uuid === convUuid) ?? false;
        if (exists) {
          qc.setQueryData<ConvListResponse>(['conversations'], (prev) => {
            if (!prev?.data) return prev;
            const updated = prev.data.map((c) =>
              c.uuid === convUuid
                ? {
                    ...c,
                    last_message: {
                      uuid: data.uuid,
                      sender_id: data.sender_id ?? '',
                      body: data.body ?? null,
                      created_at: data.created_at,
                      type: data.type,
                      is_client_sealed: data.is_client_sealed,
                    },
                    last_message_at: data.created_at ?? c.last_message_at,
                    // Si le fil est ouvert, le message est marqué lu
                    // immédiatement — ne pas incrémenter les non-lus.
                    unread_count: threadOpen
                      ? c.unread_count
                      : (c.unread_count ?? 0) + 1,
                  }
                : c,
            );
            updated.sort((a, b) =>
              (b.last_message_at ?? '') > (a.last_message_at ?? '') ? 1 : -1,
            );
            return { ...prev, data: updated };
          });
        } else {
          qc.invalidateQueries({ queryKey: ['conversations'] });
        }

        // 2. Fiche conversation (header du fil) : rafraîchir si en cache.
        qc.invalidateQueries({ queryKey: ['conversation-meta', convUuid] });

        // 3. Toast — sauf si le fil concerné est déjà ouvert à l'écran.
        if (threadOpen) return;

        const name = data.sender?.name ?? 'Nouveau message';
        const preview = data.is_client_sealed
          ? '🔐 Message sécurisé'
          : data.body
            ? data.body.slice(0, 60)
            : data.type === 'image'
              ? '📷 Photo'
              : '📎 Pièce jointe';

        showToast({
          message: `${name} : ${preview}`,
          type: 'info',
          actionLabel: 'Voir',
          onAction: () => routerRef.current.push(threadPath as never),
          durationMs: 5000,
        });
      },
    );

    return unsubscribe;
  }, [userId, qc]);
}
