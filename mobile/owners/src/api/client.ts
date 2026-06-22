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

apiClient.interceptors.request.use(async (config) => {
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
 * Pulls the API's standard `{ message, errors }` shape (Laravel
 * form-request validation responses) into a usable string for toast /
 * inline rendering. Falls back to a generic French message rather than
 * exposing raw HTTP details. When the payload carries field-level
 * `errors`, the first one is surfaced (more actionable than the generic
 * "Données invalides").
 */
export function extractApiErrorMessage(err: unknown): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;
    if (data?.errors) {
      const first = Object.values(data.errors)[0]?.[0];
      if (typeof first === 'string' && first.trim() !== '') {
        return first;
      }
    }
    const msg = data?.message;
    if (typeof msg === 'string' && msg.trim() !== '') {
      return msg;
    }
    if (err.response?.status === 401) {
      return 'Identifiants incorrects.';
    }
    if (err.response?.status === 403) {
      return 'Action non autorisée.';
    }
    if (err.response?.status === 422) {
      return 'Données invalides.';
    }
    if (err.code === 'ECONNABORTED') {
      return 'Délai d’attente dépassé. Vérifiez votre connexion.';
    }
    if (!err.response) {
      const code = err.code ?? 'ERR_NETWORK';
      return `Connexion au serveur impossible (${code}). Vérifiez votre réseau.`;
    }
  }
  if (err instanceof Error && err.message.trim() !== '') {
    return err.message;
  }
  return 'Une erreur est survenue. Réessayez plus tard.';
}
