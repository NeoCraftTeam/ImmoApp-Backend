import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { focusManager, onlineManager, QueryClient } from '@tanstack/react-query';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { useEffect, useState, type ReactNode } from 'react';
import { AppState, Platform } from 'react-native';

/**
 * Single QueryClient + offline persistence layer for the owner app.
 *
 *  - **5 min stale**, **24 h gc**: lets owners review cached lists while
 *    offline.
 *  - **AsyncStorage persister**: serialises the cache (1 s debounce),
 *    rehydrates at cold-start.
 *  - **online/focus managers**: NetInfo suspends fetches when offline;
 *    AppState refetches when the app returns to the foreground.
 *
 *  Cache versioned via `CACHE_BUSTER` — bump to invalidate all
 *  persistence after a breaking API change.
 */
const CACHE_BUSTER = 'kh-owners-v1';
const QUERY_CACHE_STORAGE_KEY = 'kh-owners-query-cache';

/**
 * Supprime le cache de queries déshydraté d'AsyncStorage. Appelé au
 * signOut : les données du compte précédent (annonces, paiements,
 * locataires…) ne doivent ni se réhydrater au prochain cold start, ni
 * rester en clair sur le disque après déconnexion.
 */
export async function clearPersistedQueryCache(): Promise<void> {
  try {
    await AsyncStorage.removeItem(QUERY_CACHE_STORAGE_KEY);
  } catch {
    /* best-effort — le buster de version couvrira les cas résiduels */
  }
}

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
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 5 * 60 * 1000,
            gcTime: 24 * 60 * 60 * 1000,
            retry: 2,
            refetchOnWindowFocus: false,
            refetchOnReconnect: true,
          },
          mutations: {
            retry: 0,
          },
        },
      }),
  );

  const [persister] = useState(() =>
    createAsyncStoragePersister({
      storage: AsyncStorage,
      key: 'kh-owners-query-cache',
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
            // Auth + live data — re-fetch fresh at each cold start.
            // Inclut le statut paiement : on ne veut JAMAIS afficher un
            // statut paiement persisté (pourrait être stale → user voit
            // "pending" alors qu'il est déjà "success").
            if (key === 'me') return false;
            if (key === 'owner-stats') return false;
            if (key === 'notifications-unread-count') return false;
            if (key === 'payment-status') return false;
            if (key === 'credits-balance') return false;
            if (key === 'subscription-current') return false;
            return query.state.status === 'success';
          },
        },
      }}
    >
      {children}
    </PersistQueryClientProvider>
  );
}
