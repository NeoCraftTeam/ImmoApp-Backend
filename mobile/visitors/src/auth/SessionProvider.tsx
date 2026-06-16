import { createContext, useContext, useMemo, type ReactNode } from 'react';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

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
  /** Posts to `/auth/register`, persists the returned token. */
  signUp: (input: SignUpInput) => Promise<void>;
  /** Clears the stored token; the Axios 401 interceptor calls this too. */
  signOut: () => void;
}

export interface SignUpInput {
  firstname: string;
  lastname: string;
  email: string;
  password: string;
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

  const value = useMemo<SessionContextValue>(
    () => ({
      token,
      isLoading,
      isAuthenticated: token !== null,
      signIn: async (email, password) => {
        const { data } = await apiClient.post<{ token: string }>(
          ENDPOINTS.auth.login,
          { email, password },
        );
        if (typeof data?.token !== 'string' || data.token === '') {
          throw new Error('Réponse de connexion invalide.');
        }
        setToken(data.token);
      },
      signUp: async (input) => {
        const { data } = await apiClient.post<{ token: string }>(
          ENDPOINTS.auth.register,
          input,
        );
        if (typeof data?.token !== 'string' || data.token === '') {
          throw new Error('Réponse d’inscription invalide.');
        }
        setToken(data.token);
      },
      signOut: () => setToken(null),
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
