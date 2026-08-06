import { useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useEffect, useRef } from 'react';

import { subscribePrivate } from '@/services/echo';
import { showToast } from '@/services/toast';

interface SearchAlertMatchPayload {
  id?: string;
  type?: string;
  title?: string;
  message?: string;
  ad_id?: string;
  ad_title?: string;
  ad_slug?: string;
  alert_id?: string;
}

/**
 * Alertes de recherche en temps réel : abonne le canal privé `user.{id}`
 * (event `search_alert.match`, diffusé par la notification Laravel via
 * son channel `broadcast`) et, dès qu'une annonce matche une alerte :
 *
 *   1. invalide le centre de notifications (badge + liste) — fini
 *      l'attente du polling 60 s ;
 *   2. affiche un toast « Voir » qui ouvre directement la fiche annonce.
 *
 * La push FCM couvre l'app en arrière-plan ; ce hook couvre l'app
 * ouverte. No-op sans user connecté ou sans config Reverb.
 */
export function useNotificationsRealtime(userId: string | undefined): void {
  const qc = useQueryClient();
  const router = useRouter();
  const routerRef = useRef(router);
  const seenRef = useRef<Set<string>>(new Set());

  useEffect(() => {
    routerRef.current = router;
  }, [router]);

  useEffect(() => {
    if (!userId) return;

    const unsubscribe = subscribePrivate(
      `user.${userId}`,
      ['search_alert.match'],
      (_event, raw) => {
        const data = raw as SearchAlertMatchPayload;

        // Dédup défensive (retry réseau / double livraison).
        if (data?.id) {
          const seen = seenRef.current;
          if (seen.has(data.id)) return;
          seen.add(data.id);
          if (seen.size > 200) {
            const oldest = seen.values().next().value;
            if (oldest !== undefined) seen.delete(oldest);
          }
        }

        // 1. Centre de notifications live.
        qc.invalidateQueries({ queryKey: ['notifications'] });

        // 2. Toast avec deep-link vers l'annonce.
        const slug = data?.ad_slug ?? data?.ad_id;
        const message =
          data?.message ??
          (data?.ad_title
            ? `${data.ad_title} correspond à votre alerte`
            : 'Une annonce correspond à votre alerte');

        showToast({
          message: `🔔 ${message}`,
          type: 'info',
          actionLabel: slug ? 'Voir' : undefined,
          onAction: slug
            ? () => routerRef.current.push(`/ads/${slug}` as never)
            : undefined,
          durationMs: 6000,
        });
      },
    );

    return unsubscribe;
  }, [userId, qc]);
}
