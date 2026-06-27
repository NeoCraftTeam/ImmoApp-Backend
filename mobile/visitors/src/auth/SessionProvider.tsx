import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react';

import { apiClient, setBearerToken } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { clearUserContext, trackEvent } from '@/services/monitoring';

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
