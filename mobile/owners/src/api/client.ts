import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import axios, { type AxiosError, type AxiosInstance } from 'axios';

import { SESSION_KEY, scopedSessionKey } from '@/auth/storage-keys';

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
const RESOLVED_BASE_URL = resolveBaseUrl();

/** URL de base API effective (env → dérivée plateforme). Sert aussi à
 *  reconstruire les URLs médias absolues (`src/lib/media-url.ts`). */
export { RESOLVED_BASE_URL };

/**
 * Clé SecureStore effective pour cette build (cloisonnée par backend).
 * Unique source de vérité — SessionProvider et utils/documents l'importent
 * d'ici pour que lecture, écriture et cleanup 401 visent la même entrée.
 */
export const SCOPED_SESSION_KEY = scopedSessionKey(RESOLVED_BASE_URL);

export const apiClient: AxiosInstance = axios.create({
  baseURL: RESOLVED_BASE_URL,
  timeout: 20_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-KeyHome-Client': 'keyhome-mobile-owners',
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

/**
 * Résout le bearer token courant (cache mémoire, puis SecureStore scoped).
 * Utilisé par les téléchargements binaires (`utils/documents.ts`) qui
 * passent par `FileSystem.downloadAsync` et non par Axios.
 */
export async function resolveBearerToken(): Promise<string | null> {
  if (bearerTokenCache) return bearerTokenCache;
  try {
    const stored = await SecureStore.getItemAsync(SCOPED_SESSION_KEY);
    if (stored && stored !== 'null' && stored !== '') {
      bearerTokenCache = stored;
      return stored;
    }
  } catch {
    /* SecureStore unavailable — anonymous. */
  }
  return null;
}

/**
 * Abonnés notifiés quand un 401 invalide la session (token révoqué /
 * expiré). Le SessionProvider s'y branche pour remettre son state React
 * à null immédiatement — sans ça l'UI restait « connectée » jusqu'au
 * prochain écran qui touche /auth/me.
 */
type UnauthorizedListener = () => void;
const unauthorizedListeners = new Set<UnauthorizedListener>();

export function onUnauthorized(listener: UnauthorizedListener): () => void {
  unauthorizedListeners.add(listener);
  return () => unauthorizedListeners.delete(listener);
}

/**
 * Routes d'auth dont un 401 signifie « mauvais identifiants », pas
 * « session expirée » — ne pas y purger la session existante.
 */
const AUTH_401_EXEMPT = ['/auth/login', '/auth/registerAgent', '/auth/verify-email-otp', '/auth/oauth/'];

apiClient.interceptors.request.use(async (config) => {
  const token = await resolveBearerToken();
  if (token) {
    config.headers = config.headers ?? {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ message?: string }>) => {
    const url = String(error.config?.url ?? '');
    const isAuthAttempt = AUTH_401_EXEMPT.some((p) => url.includes(p));
    if (error.response?.status === 401 && !isAuthAttempt) {
      bearerTokenCache = null;
      try {
        await SecureStore.deleteItemAsync(SCOPED_SESSION_KEY);
        // Nettoie aussi l'ancienne clé non-scopée (builds antérieures).
        await SecureStore.deleteItemAsync(SESSION_KEY);
      } catch {
        /* fall through */
      }
      for (const listener of unauthorizedListeners) {
        try {
          listener();
        } catch {
          /* listener must never break the error chain */
        }
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
