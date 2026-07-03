import { useMutation, useQueryClient } from '@tanstack/react-query';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import type { SocialProvider } from '@/auth/SessionProvider';

/**
 * Lie un provider social au compte AUTHENTIFIÉ via le flux redirect :
 * GET link-redirect (bearer) → ouverture in-app du consentement OAuth →
 * retour deep-link `?linked=1` (ou `?link_error=…`). Rafraîchit /me.
 */
export function useLinkProvider() {
  const qc = useQueryClient();
  return useMutation<{ linked: boolean; error?: string }, Error, SocialProvider>({
    mutationFn: async (provider) => {
      const returnUrl = Linking.createURL('auth/callback');
      const { data } = await apiClient.get<{ redirect_url?: string }>(
        ENDPOINTS.auth.oauthLinkRedirect(provider),
        { params: { redirect_uri: returnUrl } },
      );
      if (typeof data?.redirect_url !== 'string' || data.redirect_url === '') {
        throw new Error('Impossible de démarrer la liaison.');
      }
      const result = await WebBrowser.openAuthSessionAsync(data.redirect_url, returnUrl);
      if (result.type !== 'success' || !('url' in result) || !result.url) {
        return { linked: false };
      }
      const { queryParams } = Linking.parse(result.url);
      if (queryParams?.linked === '1') {
        return { linked: true };
      }
      const err = typeof queryParams?.link_error === 'string' ? queryParams.link_error : undefined;
      return { linked: false, error: err };
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['me'] });
    },
  });
}

/** Délie un provider social (DELETE /auth/oauth/{provider}/unlink). */
export function useUnlinkProvider() {
  const qc = useQueryClient();
  return useMutation<void, Error, SocialProvider>({
    mutationFn: async (provider) => {
      await apiClient.delete(ENDPOINTS.auth.oauthUnlink(provider));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['me'] });
    },
  });
}
