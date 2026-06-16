import { useCallback, useEffect, useReducer } from 'react';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

type State<T> = [boolean, T | null];
type Reducer<T> = (state: State<T>, action: T | null) => State<T>;

/**
 * `[ [isLoading, value], setValue ]` — small primitive that hides the
 * async read from SecureStore behind a hook with a sync-feeling API.
 * On Web (Expo Router supports web export) we fall back to
 * `localStorage` since SecureStore is not available there.
 *
 * The reducer keeps the loading flag flipped to `false` only after the
 * initial read resolves, so callers can render a splash until the
 * persisted value is hydrated.
 */
export function useStorageState<T extends string>(key: string): [State<T>, (value: T | null) => void] {
  const [state, dispatch] = useReducer<Reducer<T>>(
    (_state, action) => [false, action],
    [true, null],
  );

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        if (Platform.OS === 'web') {
          if (typeof window !== 'undefined' && window.localStorage) {
            const value = window.localStorage.getItem(key);
            if (!cancelled) dispatch(value as T | null);
            return;
          }
        }
        const value = await SecureStore.getItemAsync(key);
        if (!cancelled) dispatch(value as T | null);
      } catch {
        if (!cancelled) dispatch(null);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [key]);

  const setValue = useCallback(
    (value: T | null) => {
      dispatch(value);
      (async () => {
        try {
          if (Platform.OS === 'web') {
            if (typeof window === 'undefined' || !window.localStorage) return;
            if (value === null) {
              window.localStorage.removeItem(key);
            } else {
              window.localStorage.setItem(key, value);
            }
            return;
          }
          if (value === null) {
            await SecureStore.deleteItemAsync(key);
          } else {
            await SecureStore.setItemAsync(key, value);
          }
        } catch {
          /* storage unavailable — in-memory state still updated */
        }
      })();
    },
    [key],
  );

  return [state, setValue];
}
