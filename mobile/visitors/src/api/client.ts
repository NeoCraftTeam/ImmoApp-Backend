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

const RESOLVED_BASE_URL = resolveBaseUrl();

if (__DEV__) {
  // Diagnostic : confirme contre quel backend l'app tape réellement.
  // Une valeur `localhost`/IP LAN inattendue ici explique un
  // "Identifiants incorrects" alors que la prod a bien le compte.
  console.log(`[api] base URL → ${RESOLVED_BASE_URL}`);
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
  baseURL: RESOLVED_BASE_URL,
  timeout: 15_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

/**
 * Cache in-memory du bearer token — evite la race condition ou des
 * requetes concurrentes lisaient SecureStore (async ~5 ms) avant
 * que le store ait ete hydrate, certaines partaient avec
 * `Authorization` et d'autres sans. Le SessionProvider populate
 * `setBearerToken()` au boot puis a chaque signIn/signOut, donc
 * les requetes apres l'hydratation initiale lisent cache et zero
 * SecureStore (snappier + thread-safe).
 *
 * Le fallback SecureStore est garde pour les requetes qui partent
 * AVANT que le provider ait pu populate la cache (cold-start fast
 * fire). C'est une lecture lazy: si on a deja la valeur en memoire
 * on skip totalement SecureStore.
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
        bearerTokenCache = stored; // populate la cache pour les prochains calls
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
    // Treat 401 as "session ended" — clear the token so the app's
    // SessionProvider re-runs its gate. We do NOT trigger a navigation
    // from here (the API layer must not know about the router); the
    // provider observes the cleared state and redirects.
    if (error.response?.status === 401) {
      // Vider la cache en premier (sync, atomique) — sinon une
      // requete in-flight pourrait re-lire l'ancien token via
      // bearerTokenCache pendant que deleteItemAsync est encore
      // en cours (P0 token zombie). Puis cleanup SecureStore.
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
    // No response received — typiquement une erreur TLS / DNS / Wi-Fi /
    // backend down. On expose le code axios sous-jacent pour aider à
    // distinguer "Wi-Fi off" de "backend down" de "URL inaccessible".
    // (Pas de mention d'un nom d'hôte spécifique — l'app est utilisée
    // par des bailleurs hors-dev qui n'ont jamais entendu parler de
    // `keyhome.test` ; le message générique reste actionnable.)
    if (!err.response) {
      const code = err.code ?? 'ERR_NETWORK';
      return `Connexion au serveur impossible (${code}). Vérifiez votre connexion internet.`;
    }
  }
  // Non-axios Error — surface its own message instead of the generic
  // fallback so caller code's `throw new Error('…')` actually reaches the UI.
  if (err instanceof Error && err.message.trim() !== '') {
    return err.message;
  }
  return 'Une erreur est survenue. Réessayez plus tard.';
}
