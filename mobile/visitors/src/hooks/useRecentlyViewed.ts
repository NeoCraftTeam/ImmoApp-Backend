import AsyncStorage from '@react-native-async-storage/async-storage';
import { useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import type { Ad } from '@/types/ad';

// Cloisonne le cache par environnement d'API : les annonces d'une base
// (ex. prod) n'existent pas forcément sur une autre (ex. preprod) — sans
// ce suffixe, une annonce cachée renvoie « Ressource introuvable » au clic
// après un changement de serveur.
const ENV_SUFFIX = String(apiClient.defaults.baseURL ?? 'default')
  .replace(/[^a-z0-9]/gi, '')
  .slice(-24);
const STORAGE_KEY = `kh-recently-viewed:${ENV_SUFFIX}`;
const MAX_ITEMS = 10;
const QUERY_KEY = ['recently-viewed', ENV_SUFFIX] as const;

async function readRecent(): Promise<Ad[]> {
  try {
    const raw = await AsyncStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return [];
    }
    const parsed = JSON.parse(raw) as unknown;
    return Array.isArray(parsed) ? (parsed as Ad[]) : [];
  } catch {
    return [];
  }
}

/**
 * Liste des annonces récemment consultées (persistée en AsyncStorage,
 * plafonnée à 10). Alimente le carrousel « Récemment consultés » de
 * l'accueil — réplique la section web du même nom.
 */
export function useRecentlyViewed() {
  return useQuery<Ad[]>({
    queryKey: QUERY_KEY,
    queryFn: readRecent,
    staleTime: 0,
    gcTime: 24 * 60 * 60 * 1000,
  });
}

/**
 * Renvoie une fonction `record(ad)` à appeler quand l'utilisateur ouvre
 * le détail d'une annonce. Déduplique par id, place l'annonce en tête,
 * tronque à `MAX_ITEMS`, et met à jour le cache Query pour que l'accueil
 * se rafraîchisse sans refetch.
 */
export function useRecordRecentlyViewed() {
  const qc = useQueryClient();

  return useCallback(
    async (ad: Ad) => {
      if (!ad?.id) {
        return;
      }
      const current = await readRecent();
      const next = [ad, ...current.filter((a) => a.id !== ad.id)].slice(0, MAX_ITEMS);
      try {
        await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      } catch {
        /* stockage indisponible — on garde au moins l'état en mémoire. */
      }
      qc.setQueryData<Ad[]>(QUERY_KEY, next);
    },
    [qc],
  );
}

/**
 * Retire une annonce du cache « récemment consultés » (par id OU slug) —
 * à appeler quand son détail renvoie 404, pour ne pas garder une annonce
 * fantôme (supprimée, ou d'un autre environnement) dans le carrousel.
 */
export function useRemoveRecentlyViewed() {
  const qc = useQueryClient();

  return useCallback(
    async (idOrSlug: string) => {
      if (!idOrSlug) {
        return;
      }
      const current = await readRecent();
      const next = current.filter((a) => a.id !== idOrSlug && a.slug !== idOrSlug);
      if (next.length === current.length) {
        return;
      }
      try {
        await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      } catch {
        /* stockage indisponible */
      }
      qc.setQueryData<Ad[]>(QUERY_KEY, next);
    },
    [qc],
  );
}
