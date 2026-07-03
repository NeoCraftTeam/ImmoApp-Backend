import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';

import { apiClient, setBearerToken } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { clearUserContext, trackEvent } from '@/services/monitoring';

export type SocialProvider = 'google' | 'facebook' | 'github';

import { SESSION_KEY } from './storage-keys';
import { useStorageState } from './useStorageState';

interface SessionContextValue {
  /** Persisted bearer token, or null when the user is anonymous. */
  token: string | null;
  /** True until the initial SecureStore read resolves. */
  isLoading: boolean;
  /** Convenience flag — `token !== null`. */
  isAuthenticated: boolean;
  /** Posts to `/auth/login`, persists the returned token, resolves on success. */
  signIn: (email: string, password: string) => Promise<void>;
  /**
   * Social login via the backend redirect flow (Google / Facebook / GitHub).
   * Opens the provider's OAuth page in a system browser, captures the
   * one-time `exchange_code` from the deep-link callback, and swaps it for
   * a Sanctum token. Resolves on success, resolves silently if the user
   * cancels, throws on failure.
   */
  signInWithProvider: (provider: SocialProvider) => Promise<{ cancelled: boolean }>;
  /**
   * Posts to `/auth/registerCustomer`. Returns the backend's
   * `email_verification_required` flag so the caller can route to
   * `verify-otp` or straight to home.
   */
  signUp: (input: SignUpInput) => Promise<{ emailVerificationRequired: boolean }>;
  /** Install a token returned by an external flow (e.g. OTP verify response). */
  setToken: (token: string) => void;
  /** Clears the stored token; the Axios 401 interceptor calls this too. */
  signOut: () => void;
}

export interface SignUpInput {
  firstname: string;
  lastname: string;
  email: string;
  phone_number: string;
  password: string;
  confirm_password: string;
}

/**
 * Backend response from `/auth/login` + `/auth/register`. Laravel ships
 * `access_token`; older drafts used `token` — we accept both so older
 * mocks / fixtures don't silently swallow the value.
 */
interface LoginResponse {
  access_token?: string;
  token?: string;
  message?: string;
  expires_at?: string;
  email_verification_required?: boolean;
}

const SessionContext = createContext<SessionContextValue | null>(null);

/**
 * Provides the authenticated session to any screen under `<SessionProvider>`.
 * Screens should call `useSession()` and react to `isAuthenticated` — the
 * root layout uses it to gate the (auth) vs (app) route groups, but
 * individual screens can also branch on it (e.g. show "Connectez-vous
 * pour ajouter aux favoris" on the ad-detail page).
 */
export function SessionProvider({ children }: { children: ReactNode }) {
  const [[isLoading, token], setToken] = useStorageState<string>(SESSION_KEY);

  // Sync le bearer-token cache de `apiClient` chaque fois que le token
  // bouge. Sans ca, la cache in-memory de client.ts (qui evite la
  // race condition sur les reads SecureStore concurrents) restait
  // desynchronisee apres signIn/signOut — les premieres requetes
  // post-login partaient sans Authorization, les post-logout
  // continuaient avec l'ancien token.
  useEffect(() => {
    setBearerToken(token);
  }, [token]);

  const value = useMemo<SessionContextValue>(
    () => ({
      token,
      isLoading,
      isAuthenticated: token !== null,
      signIn: async (email, password) => {
        const { data } = await apiClient.post<LoginResponse>(
          ENDPOINTS.auth.login,
          { email, password },
        );
        const accessToken = data?.access_token ?? data?.token;
        if (typeof accessToken !== 'string' || accessToken === '') {
          throw new Error('Réponse de connexion invalide.');
        }
        // Sync direct la cache AVANT de setToken (qui re-render
        // les consumers) — garantit que les hooks qui fire des
        // requetes au moment du re-render ont deja le bon token.
        setBearerToken(accessToken);
        setToken(accessToken);
        trackEvent('auth.signIn', { email });
      },
      signInWithProvider: async (provider) => {
        // Deep link de retour (keyhome://auth/callback en build natif,
        // exp://…/--/auth/callback en Expo Go). Le backend le reçoit comme
        // `redirect_uri` et y renvoie le `exchange_code` après OAuth.
        const returnUrl = Linking.createURL('auth/callback');

        const { data: redirect } = await apiClient.get<{ redirect_url?: string }>(
          ENDPOINTS.auth.oauthRedirect(provider),
          { params: { redirect_uri: returnUrl } },
        );
        if (typeof redirect?.redirect_url !== 'string' || redirect.redirect_url === '') {
          throw new Error('Impossible de démarrer la connexion.');
        }

        const result = await WebBrowser.openAuthSessionAsync(redirect.redirect_url, returnUrl);
        if (result.type !== 'success' || !('url' in result) || !result.url) {
          // L'utilisateur a fermé le navigateur — pas une erreur.
          return { cancelled: true };
        }

        const { queryParams } = Linking.parse(result.url);
        const code = queryParams?.exchange_code;
        if (typeof code !== 'string' || code === '') {
          throw new Error('Échec de l’authentification. Veuillez réessayer.');
        }

        const { data } = await apiClient.get<LoginResponse>(ENDPOINTS.auth.oauthExchange, {
          params: { exchange_code: code },
        });
        const accessToken = data?.access_token ?? data?.token;
        if (typeof accessToken !== 'string' || accessToken === '') {
          throw new Error('Réponse de connexion invalide.');
        }
        setBearerToken(accessToken);
        setToken(accessToken);
        trackEvent('auth.signInSocial', { provider });
        return { cancelled: false };
      },
      signUp: async (input) => {
        const { data } = await apiClient.post<LoginResponse>(
          ENDPOINTS.auth.register,
          input,
        );
        const accessToken = data?.access_token ?? data?.token;
        const emailVerificationRequired = Boolean(data?.email_verification_required);
        if (typeof accessToken === 'string' && accessToken !== '') {
          setBearerToken(accessToken);
          setToken(accessToken);
        } else if (!emailVerificationRequired) {
          throw new Error('Réponse d’inscription invalide.');
        }
        return { emailVerificationRequired };
      },
      setToken: (next: string) => {
        setBearerToken(next);
        setToken(next);
      },
      signOut: () => {
        trackEvent('auth.signOut');
        clearUserContext();
        // Révoque le token côté serveur (best-effort, AVANT de vider le
        // bearer local — sinon la requête part sans Authorization).
        void apiClient.post(ENDPOINTS.auth.logout).catch(() => {});
        // Vider la cache AVANT le setToken — sinon une derniere
        // requete qui fire pendant le re-render lit encore l'ancien
        // token et envoie une auth zombie.
        setBearerToken(null);
        setToken(null);
      },
    }),
    [token, isLoading, setToken],
  );

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionContextValue {
  const ctx = useContext(SessionContext);
  if (!ctx) {
    throw new Error('useSession must be used within <SessionProvider>');
  }
  return ctx;
}
