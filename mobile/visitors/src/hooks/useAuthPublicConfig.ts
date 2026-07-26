import { useQuery } from '@tanstack/react-query';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export type GoogleAuthMethod = 'clerk' | 'socialite' | 'unavailable';

export interface AuthPublicConfig {
  clerk: {
    enabled: boolean;
    publishable_key: string | null;
    oauth_providers?: string[];
  };
  socialite: Record<string, boolean>;
  google: {
    method: GoogleAuthMethod;
  };
}

interface AuthPublicConfigEnvelope {
  data?: AuthPublicConfig;
}

const AUTH_CONFIG_QUERY_KEY = ['config', 'auth'] as const;

/** Même clé publique que keyhome.app — utilisée tant que GET /config/auth n'est pas déployé. */
export function buildFallbackAuthPublicConfig(): AuthPublicConfig {
  const clerkKey = process.env.EXPO_PUBLIC_CLERK_PUBLISHABLE_KEY?.trim() ?? null;
  const clerkEnabled = clerkKey !== null && clerkKey !== '';

  return {
    clerk: {
      enabled: clerkEnabled,
      publishable_key: clerkKey,
      oauth_providers: clerkEnabled ? ['google', 'facebook', 'github'] : [],
    },
    socialite: {
      google: false,
      facebook: false,
      github: false,
    },
    google: {
      method: clerkEnabled ? 'clerk' : 'socialite',
    },
  };
}

async function fetchAuthPublicConfig(): Promise<AuthPublicConfig> {
  try {
    const { data } = await apiClient.get<AuthPublicConfigEnvelope>(ENDPOINTS.config.auth);
    const payload = data?.data;
    if (payload) {
      return payload;
    }
  } catch {
    // Prod pas encore déployée avec /config/auth — repli local.
  }

  return buildFallbackAuthPublicConfig();
}

export function useAuthPublicConfig() {
  return useQuery({
    queryKey: AUTH_CONFIG_QUERY_KEY,
    queryFn: fetchAuthPublicConfig,
    staleTime: 5 * 60_000,
    retry: 1,
    placeholderData: buildFallbackAuthPublicConfig(),
  });
}

export function resolveClerkPublishableKey(config: AuthPublicConfig | undefined): string | null {
  const fromEnv = process.env.EXPO_PUBLIC_CLERK_PUBLISHABLE_KEY?.trim();
  if (fromEnv) {
    return fromEnv;
  }

  const fromApi = config?.clerk.publishable_key?.trim();
  if (fromApi) {
    return fromApi;
  }

  return null;
}
