import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import axios, { type AxiosError, type AxiosInstance } from 'axios';

import { SESSION_KEY } from '@/auth/storage-keys';

/**
 * Resolve the API base URL, preferring an explicit `EXPO_PUBLIC_API_BASE_URL`
 * env var (set by devs running the Laravel backend on their LAN IP), then
 * the dev/prod defaults baked into `app.json`'s `extra` block.
 *
 * `__DEV__` is React Native's global flag — true when running through
 * Metro, false in EAS production builds.
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
 *
 * Token refresh strategy: Sanctum's personal access tokens don't expire
 * by default; on a 401 we clear the stored token and let the UI redirect
 * the user to the login screen. If the backend ever moves to short-lived
 * + refresh tokens, the `response` interceptor below is the place to add
 * the retry-after-refresh dance.
 */
export const apiClient: AxiosInstance = axios.create({
  baseURL: resolveBaseUrl(),
  timeout: 15_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

apiClient.interceptors.request.use(async (config) => {
  // SecureStore reads are async and adds ~5 ms per request. The session
  // provider caches the token in memory once mounted, so reads after the
  // first navigation are cheap (provider-tracked); this read is the
  // safety net for screens that fire requests before the provider has
  // hydrated (e.g. SSR-like first paint on Web export).
  try {
    const token = await SecureStore.getItemAsync(SESSION_KEY);
    if (token && token !== 'null') {
      config.headers = config.headers ?? {};
      config.headers.Authorization = `Bearer ${token}`;
    }
  } catch {
    /* SecureStore unavailable (e.g. Web export) — anonymous request. */
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ message?: string }>) => {
    // Treat 401 as "session ended" — clear the token so the app's
    // SessionProvider re-runs its gate. We do NOT trigger a navigation
    // from here (the API layer must not know about the router); the
    // provider observes the cleared state and redirects.
    if (error.response?.status === 401) {
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
 * Helper that pulls the API's standard `{ message, errors }` shape
 * (Laravel form-request validation responses) into a usable string for
 * toast / inline rendering. Falls back to a generic French error
 * message rather than exposing raw HTTP details.
 */
export function extractApiErrorMessage(err: unknown): string {
  if (axios.isAxiosError(err)) {
    const msg = (err.response?.data as { message?: string } | undefined)?.message;
    if (typeof msg === 'string' && msg.trim() !== '') {
      return msg;
    }
    if (err.response?.status === 401) {
      return 'Identifiants incorrects.';
    }
    if (err.response?.status === 422) {
      return 'Données invalides.';
    }
    if (err.code === 'ECONNABORTED') {
      return 'Délai d’attente dépassé. Vérifiez votre connexion.';
    }
    // No response received — typically a TLS / DNS / connectivity error.
    // Surfacing the underlying axios error code helps the user (and us)
    // distinguish "Wi-Fi off" from "backend down" from "untrusted cert".
    if (!err.response) {
      const code = err.code ?? 'ERR_NETWORK';
      return `Connexion au serveur impossible (${code}). Vérifiez votre réseau et le certificat keyhome.test.`;
    }
  }
  // Non-axios Error — surface its own message instead of the generic
  // fallback so caller code's `throw new Error('…')` actually reaches the UI.
  if (err instanceof Error && err.message.trim() !== '') {
    return err.message;
  }
  return 'Une erreur est survenue. Réessayez plus tard.';
}
