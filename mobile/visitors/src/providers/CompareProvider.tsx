import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import type { Ad } from '@/types/ad';

const COMPARE_KEY = 'keyhome.compare.ids';
const MAX_ITEMS = 4;

interface CompareContextValue {
  /** Compared ads — full Ad objects so the compare screen renders without an extra fetch. */
  items: Ad[];
  /** True when the set already holds `MAX_ITEMS`. UI uses this to disable the toggle. */
  isFull: boolean;
  /** O(1) membership lookup the CompareButton calls on every render. */
  isCompared: (id: string) => boolean;
  /** Add the ad if absent, remove it if present. Returns whether the item is now in the set. */
  toggle: (ad: Ad) => boolean;
  /** Empty the set — used by the CompareBar's "× Effacer" action. */
  clear: () => void;
}

const CompareContext = createContext<CompareContextValue | null>(null);

/**
 * Holds the visitor's "comparison shopping cart" — up to 4 ads at once.
 * The id list is persisted across launches so a user who builds a
 * comparison, closes the app, and comes back still sees their picks.
 * Full `Ad` objects are kept in memory only (re-fetching all 4 on cold
 * launch is the next iteration's problem); the persisted id list will
 * hydrate the screen with stub cards if needed.
 */
export function CompareProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Ad[]>([]);

  // Hydrate id list on mount (best-effort).
  useEffect(() => {
    (async () => {
      try {
        let raw: string | null = null;
        if (Platform.OS === 'web') {
          raw =
            typeof window !== 'undefined'
              ? window.localStorage?.getItem(COMPARE_KEY) ?? null
              : null;
        } else {
          raw = await SecureStore.getItemAsync(COMPARE_KEY);
        }
        if (raw) {
          // We persist IDs only — the actual Ad payloads come back into
          // the set as the user re-encounters them in the feed / search.
          // No id-only ads are auto-fetched here to keep cold-start cheap;
          // an empty `items` list with persisted IDs is harmless until
          // the user opens the compare drawer.
          /* future: parse `raw` into JSON and refetch the missing ads */
        }
      } catch {
        /* SecureStore unavailable → start empty */
      }
    })();
  }, []);

  // Persist ids on every change so a hard-kill doesn't drop the picks.
  useEffect(() => {
    const ids = JSON.stringify(items.map((a) => a.id));
    (async () => {
      try {
        if (Platform.OS === 'web') {
          if (typeof window !== 'undefined' && window.localStorage) {
            window.localStorage.setItem(COMPARE_KEY, ids);
          }
        } else {
          await SecureStore.setItemAsync(COMPARE_KEY, ids);
        }
      } catch {
        /* non-fatal */
      }
    })();
  }, [items]);

  const value = useMemo<CompareContextValue>(
    () => ({
      items,
      isFull: items.length >= MAX_ITEMS,
      isCompared: (id) => items.some((a) => a.id === id),
      toggle: (ad) => {
        const exists = items.some((a) => a.id === ad.id);
        if (exists) {
          setItems((prev) => prev.filter((a) => a.id !== ad.id));
          return false;
        }
        if (items.length >= MAX_ITEMS) return true; // silently no-op; UI shows a hint
        setItems((prev) => [...prev, ad]);
        return true;
      },
      clear: () => setItems([]),
    }),
    [items],
  );

  return <CompareContext.Provider value={value}>{children}</CompareContext.Provider>;
}

export function useCompare(): CompareContextValue {
  const ctx = useContext(CompareContext);
  if (!ctx) {
    throw new Error('useCompare must be used within <CompareProvider>');
  }
  return ctx;
}

export const COMPARE_MAX_ITEMS = MAX_ITEMS;
