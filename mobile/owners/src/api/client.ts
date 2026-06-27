import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import axios, { type AxiosError, type AxiosInstance } from 'axios';

import { SESSION_KEY } from '@/auth/storage-keys';

/**
 * Resolve the API base URL, preferring an explicit `EXPO_PUBLIC_API_BASE_URL`
 * env var (set by devs running the Laravel backend on their LAN IP), then
 * the dev/prod defaults baked into `app.json`'s `extra` block.
 */
function resolveBaseUrl(): string {
  const envUrl = process.env.EXPO_PUBLIC_API_BASE_URL;
  if (envUrl && envUrl.trim() !== '') {
    return envUrl.trim();
  }
  const extra = (Constants.expoConfig?.extra ?? {}) as {
    apiBaseUrl?: string;
    apiBaseUrlDev?: string;
  };
  if (__DEV__ && extra.apiBaseUrlDev) {
    return extra.apiBaseUrlDev;
  }
  return extra.apiBaseUrl ?? 'https://api.keyhome.app/api/v1';
}

/**
 * Singleton Axios instance. Use `apiClient.get(...)`/`apiClient.post(...)`
 * everywhere — never call `axios.<method>` directly so this client's
 * interceptors (auth header, 401 handling, error normalisation) are
 * always applied.
 */
export const apiClient: AxiosInstance = axios.create({
  baseURL: resolveBaseUrl(),
  timeout: 20_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

/**
 * Cache in-memory du bearer token — voir client.ts visiteur pour
 * le rationale (race condition reads SecureStore concurrents).
 * Le SessionProvider populate via `setBearerToken()` au boot, signIn,
 * signOut.
 */
let bearerTokenCache: string | null = null;

export function setBearerToken(token: string | null): void {
  bearerTokenCache = token && token !== 'null' && token !== '' ? token : null;
}

apiClient.interceptors.request.use(async (config) => {
  let token: string | null = bearerTokenCache;
  if (token === null) {
    try {
      const stored = await SecureStore.getItemAsync(SESSION_KEY);
      if (stored && stored !== 'null' && stored !== '') {
        token = stored;
        bearerTokenCache = stored;
      }
    } catch {
      /* SecureStore unavailable (e.g. Web export) — anonymous request. */
    }
  }
  if (token) {
    config.headers = config.headers ?? {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ message?: string }>) => {
    if (error.response?.status === 401) {
      bearerTokenCache = null;
      try {
        await SecureStore.deleteItemAsync(SESSION_KEY);
      } catch {
        /* fall through */
      }
    }
    return Promise.reject(error);
  },
);

/**
 * Re-export pour rétro-compatibilité — voir `src/api/extract-error.ts`.
 * On garde l'extraction dans son propre module sans deps natives pour
 * pouvoir le couvrir par Jest sans tirer tout l'écosystème Expo.
 */
export { extractApiErrorMessage } from './extract-error';
