import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { focusManager, onlineManager, QueryClient } from '@tanstack/react-query';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { useEffect, useState, type ReactNode } from 'react';
import { AppState, Platform } from 'react-native';

import { apiClient } from '@/api/client';
import { NON_PERSISTED_QUERY_ROOTS } from '@/lib/query-keys';

/**
 * Single QueryClient + offline persistence layer.
 *
 *  - **5 min stale**, **24 h gc** : matches backend CDN cache + lets
 *    users browse cached pages while offline.
 *  - **AsyncStorage persister** : sérialise tout le cache toutes les
 *    1 s (debounce intégré), réhydrate au cold-start → l'utilisateur
 *    voit son feed même sans réseau.
 *  - **online/focus managers** : on délègue à `NetInfo` pour suspendre
 *    les fetch quand offline, et à `AppState` pour refresh quand l'app
 *    revient au premier plan (équivalent `refetchOnWindowFocus` web
 *    mais piloté par les events natifs RN).
 *
 *  Cache versionné via `CACHE_BUSTER` — bump pour invalider toute
 *  persistance après un changement breaking côté API.
 */
const CACHE_BUSTER = 'kh-v2-wallet-fresh';
const QUERY_CACHE_STORAGE_KEY = 'kh-query-cache';

/**
 * Supprime le cache de queries déshydraté d'AsyncStorage. Appelé au
 * signOut : les données user-spécifiques du compte précédent (favoris,
 * réservations, conversations…) ne doivent ni se réhydrater au prochain
 * cold start, ni rester en clair sur le disque après déconnexion.
 */
export async function clearPersistedQueryCache(): Promise<void> {
  try {
    await AsyncStorage.removeItem(QUERY_CACHE_STORAGE_KEY);
  } catch {
    /* best-effort — le buster de version couvrira les cas résiduels */
  }
}

// Online / offline detection branchée sur NetInfo (single subscription).
onlineManager.setEventListener((setOnline) => {
  return NetInfo.addEventListener((state) => {
    setOnline(!!state.isConnected && state.isInternetReachable !== false);
  });
});

function onAppStateChange(status: string) {
  if (Platform.OS !== 'web') {
    focusManager.setFocused(status === 'active');
  }
}

export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(() => {
    const qc = new QueryClient({
      defaultOptions: {
        queries: {
          staleTime: 5 * 60 * 1000,
          gcTime: 24 * 60 * 60 * 1000,
          retry: 2,
          refetchOnWindowFocus: false,
          refetchOnReconnect: true,
        },
        mutations: {
          // networkMode 'online' (défaut) : une mutation lancée hors
          // ligne est mise en PAUSE (pas échouée) puis reprise à la
          // reconnexion. On rejoue jusqu'à 3× en cas d'erreur transitoire.
          retry: 3,
          retryDelay: (attempt) => Math.min(1000 * 2 ** attempt, 30_000),
        },
      },
    });

    // mutationDefaults par clé : indispensable pour que les mutations
    // mises en pause hors-ligne et **persistées** (voir shouldDehydrate
    // Mutation) retrouvent leur mutationFn après un cold-start et soient
    // rejouées. L'optimistic update vit dans le hook (composant monté) ;
    // ici on ne (re)définit que le POST réseau rejouable.
    qc.setMutationDefaults(['toggle-favorite'], {
      mutationFn: async ({ adId }: { adId: string }) => {
        const { data } = await apiClient.post(`/ads/${adId}/favorite`);
        return data;
      },
    });

    return qc;
  });

  const [persister] = useState(() =>
    createAsyncStoragePersister({
      storage: AsyncStorage,
      key: 'kh-query-cache',
      throttleTime: 1000,
    }),
  );

  useEffect(() => {
    const sub = AppState.addEventListener('change', onAppStateChange);
    return () => sub.remove();
  }, []);

  return (
    <PersistQueryClientProvider
      client={client}
      persistOptions={{
        persister,
        maxAge: 24 * 60 * 60 * 1000,
        buster: CACHE_BUSTER,
        dehydrateOptions: {
          shouldDehydrateQuery: (query) => {
            const key = query.queryKey[0];
            if (typeof key !== 'string') return false;
            // Auth + portefeuille — jamais persistés (solde / historique doivent
            // refléter l'API courante, pas un snapshot preprod obsolète).
            if (NON_PERSISTED_QUERY_ROOTS.has(key)) return false;
            return query.state.status === 'success';
          },
          // Persiste les mutations en pause (actions faites hors-ligne)
          // pour qu'elles survivent à la fermeture de l'app et soient
          // rejouées au retour du réseau.
          shouldDehydrateMutation: (mutation) => mutation.state.isPaused,
        },
      }}
      onSuccess={() => {
        // Après réhydratation du cache : rejoue les mutations mises en
        // pause hors-ligne lors de la session précédente.
        void client.resumePausedMutations();
      }}
    >
      {children}
    </PersistQueryClientProvider>
  );
}
