import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';

import { apiClient, setBearerToken } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import {
  clearUserContext,
  trackEvent,
} from '@/services/monitoring';

import { SESSION_KEY } from './storage-keys';
import { useStorageState } from './useStorageState';

interface SessionContextValue {
  /** Persisted bearer token, or null when the user is signed out. */
  token: string | null;
  /** True until the initial SecureStore read resolves. */
  isLoading: boolean;
  /** Convenience flag — `token !== null`. */
  isAuthenticated: boolean;
  /** Posts to `/auth/login`, persists the returned token. */
  signIn: (email: string, password: string) => Promise<void>;
  /**
   * Posts to `/auth/registerAgent`. Returns the backend's
   * `email_verification_required` flag so the caller can route to
   * `verify-otp` or straight to the dashboard.
   */
  signUp: (input: SignUpInput) => Promise<{ emailVerificationRequired: boolean }>;
  /**
   * OAuth via le navigateur système, avec `role=agent` — le backend
   * crée le compte bailleur (ou connecte le compte existant sans
   * toucher à son rôle). `cancelled` quand l'utilisateur ferme le
   * navigateur sans finir.
   */
  signInWithProvider: (provider: SocialProvider) => Promise<{ cancelled: boolean }>;
  /** Install a token returned by an external flow (e.g. OTP verify). */
  setToken: (token: string) => void;
  /** Clears the stored token; the Axios 401 interceptor calls this too. */
  signOut: () => void;
}

export type SocialProvider = 'google' | 'facebook' | 'github';

export interface SignUpInput {
  firstname: string;
  lastname: string;
  email: string;
  phone_number: string;
  password: string;
  password_confirmation: string;
  agency_id?: string | null;
}

/**
 * Backend response from `/auth/login` + register. Laravel ships
 * `access_token`; older drafts used `token` — we accept both.
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
 * Provides the authenticated owner session to every screen. The root
 * layout uses `isAuthenticated` to gate the (auth) vs (tabs) route
 * groups — unlike the visitor app, the owner app requires sign-in
 * before any dashboard surface is reachable.
 */
export function SessionProvider({ children }: { children: ReactNode }) {
  const [[isLoading, token], setToken] = useStorageState<string>(SESSION_KEY);

  // Sync le bearer-token cache de apiClient — voir client.ts pour
  // le rationale (cache in-memory pour eviter SecureStore race).
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
        // Sync cache AVANT setToken → premieres requetes post-login
        // ont deja le bon token (sinon race avec le re-render).
        setBearerToken(accessToken);
        setToken(accessToken);
        trackEvent('auth.signIn', { email });
      },
      signUp: async (input) => {
        const { data } = await apiClient.post<LoginResponse>(
          ENDPOINTS.auth.register,
          input,
        );
        const accessToken = data?.access_token ?? data?.token;
        const emailVerificationRequired = Boolean(
          data?.email_verification_required,
        );
        if (typeof accessToken === 'string' && accessToken !== '') {
          setBearerToken(accessToken);
          setToken(accessToken);
        } else if (!emailVerificationRequired) {
          throw new Error('Réponse d’inscription invalide.');
        }
        return { emailVerificationRequired };
      },
      signInWithProvider: async (provider) => {
        // Deep link de retour (keyhomeowners://auth/callback en build
        // natif) — whitelisté côté backend (OAUTH_ALLOWED_REDIRECT_SCHEMES).
        const returnUrl = Linking.createURL('auth/callback');

        const { data: redirect } = await apiClient.get<{ redirect_url?: string }>(
          ENDPOINTS.auth.oauthRedirect(provider),
          { params: { redirect_uri: returnUrl, role: 'agent' } },
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
      setToken: (next: string) => {
        setBearerToken(next);
        setToken(next);
      },
      signOut: () => {
        trackEvent('auth.signOut');
        clearUserContext();
        // Vider cache AVANT le setToken pour eviter qu'une
        // derniere requete in-flight lise un token zombie.
        setBearerToken(null);
        setToken(null);
      },
    }),
    [token, isLoading, setToken],
  );

  return (
    <SessionContext.Provider value={value}>{children}</SessionContext.Provider>
  );
}

export function useSession(): SessionContextValue {
  const ctx = useContext(SessionContext);
  if (!ctx) {
    throw new Error('useSession must be used within <SessionProvider>');
  }
  return ctx;
}
