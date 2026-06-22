import { createContext, useContext, useMemo, type ReactNode } from 'react';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

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
  /** Install a token returned by an external flow (e.g. OTP verify). */
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
        setToken(accessToken);
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
          setToken(accessToken);
        } else if (!emailVerificationRequired) {
          throw new Error('Réponse d’inscription invalide.');
        }
        return { emailVerificationRequired };
      },
      setToken: (next: string) => setToken(next),
      signOut: () => setToken(null),
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
