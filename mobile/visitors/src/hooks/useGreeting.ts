import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';
import { useMe } from '@/hooks/useMe';
import type { AuthUser } from '@/types/user';

const RETURN_THRESHOLD_MS = 24 * 60 * 60 * 1000; // 24 heures

/**
 * Module-level flag — équivalent du `sessionStorage` web (qui n'existe
 * pas en React Native). Reset à chaque cold-start de l'app, ce qui
 * suffit pour éviter de spammer le backend pendant la même session
 * tout en remettant le "Bon retour" à zéro après une vraie sortie.
 */
let trackedThisSession = false;

function getTimeBasedGreeting(): string {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 12) return 'Bonjour';
  if (hour >= 12 && hour < 18) return 'Bon après-midi';
  if (hour >= 18 && hour < 21) return 'Bonsoir';
  return 'Il se fait tard';
}

/**
 * Mobile port du `useGreeting` web — même règles :
 *  - Salutation temporelle selon l'heure locale
 *  - "Bon retour parmi nous" si l'utilisateur n'a pas ouvert le home
 *    depuis 24 h+ (lu sur `user.last_home_visit_at`)
 *  - Tracking unique par session via un flag mémoire + `POST /auth/track-home-visit`
 *    qui met à jour `last_home_visit_at` côté serveur (et le cache local)
 *
 * Refresh chaque minute pour que le passage 11h59 → 12h00 bascule la
 * salutation sans devoir recharger l'écran.
 */
export function useGreeting(): string {
  const { isAuthenticated } = useSession();
  const me = useMe(isAuthenticated);
  const queryClient = useQueryClient();

  const [greeting, setGreeting] = useState<string>(getTimeBasedGreeting);

  // Refresh toutes les minutes pour suivre le franchissement d'une plage horaire
  useEffect(() => {
    const id = setInterval(() => setGreeting((g) => {
      const next = getTimeBasedGreeting();
      return g === 'Bon retour parmi nous' ? g : next;
    }), 60_000);
    return () => clearInterval(id);
  }, []);

  // Décision "Bon retour parmi nous" + tracking
  useEffect(() => {
    if (!isAuthenticated || !me.data) {
      setGreeting(getTimeBasedGreeting());
      return;
    }

    const lastVisit = me.data.last_home_visit_at
      ? new Date(me.data.last_home_visit_at).getTime()
      : 0;
    const now = Date.now();
    const isReturning =
      lastVisit > 0 && now - lastVisit > RETURN_THRESHOLD_MS;
    setGreeting(isReturning ? 'Bon retour parmi nous' : getTimeBasedGreeting());

    if (trackedThisSession) return;
    trackedThisSession = true;
    apiClient
      .post<{ last_home_visit_at: string }>(ENDPOINTS.auth.trackHomeVisit)
      .then(({ data }) => {
        // Mise à jour locale du cache /me — évite un GET /me round-trip
        const fresh = data?.last_home_visit_at;
        if (!fresh) return;
        queryClient.setQueryData<{ data: AuthUser }>(['me'], (prev) =>
          prev ? { data: { ...prev.data, last_home_visit_at: fresh } } : prev,
        );
      })
      .catch(() => {
        /* tracking best-effort — silencieux si offline */
      });
  }, [isAuthenticated, me.data, queryClient]);

  return greeting;
}
