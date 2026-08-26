import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert } from 'react-native';
import { Paragraph, Spinner, YStack } from 'tamagui';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { isOAuthFlowActive } from '@/auth/oauth-flow';
import { consumePendingRoute } from '@/auth/pending-route';
import { useSession } from '@/auth/SessionProvider';

/**
 * Deep-link landing OAuth (`keyhome://auth/callback`).
 *
 * Flux chaud : `openAuthSessionAsync` / `startSSOFlow` consomme le
 * retour et échange le code — cet écran ne fait alors qu'afficher un
 * spinner (garde `isOAuthFlowActive`, l'échange y est à usage unique).
 *
 * Flux froid : Android peut tuer le process pendant le navigateur
 * système ; l'OS relance l'app directement ici avec `exchange_code`
 * dans l'URL. On échange nous-mêmes le code contre le token Sanctum au
 * lieu de le jeter — sinon la connexion « ne fait rien ».
 */
export default function AuthCallbackScreen() {
  const router = useRouter();
  const { isAuthenticated, setToken } = useSession();
  const params = useLocalSearchParams<{
    exchange_code?: string;
    linked?: string;
    link_error?: string;
  }>();
  const [failed, setFailed] = useState(false);
  const startedRef = useRef(false);

  useEffect(() => {
    if (startedRef.current) {
      return;
    }
    startedRef.current = true;

    const exchangeCode =
      typeof params.exchange_code === 'string' && params.exchange_code !== ''
        ? params.exchange_code
        : null;
    const linked = typeof params.linked === 'string' && params.linked !== '';
    const linkError = typeof params.link_error === 'string' && params.link_error !== '';

    // Retour de liaison de comptes (flux « lier Google/Facebook… ») —
    // on route vers l'écran sécurité qui affiche l'état des liaisons.
    if (linked || linkError) {
      if (linkError) {
        Alert.alert('Liaison impossible', 'La liaison du compte a échoué. Réessayez.');
      }
      router.replace((isAuthenticated ? '/security' : '/(auth)/login') as never);
      return;
    }

    // Flux chaud en cours : le SessionProvider échange le code — on
    // laisse la main (avec un filet si sa navigation n'aboutit pas).
    if (isOAuthFlowActive()) {
      const timer = setTimeout(() => {
        router.replace((isAuthenticated ? '/(tabs)/home' : '/(auth)/login') as never);
      }, 8000);
      return () => clearTimeout(timer);
    }

    if (!exchangeCode) {
      router.replace((isAuthenticated ? '/(tabs)/home' : '/(auth)/login') as never);
      return;
    }

    if (isAuthenticated) {
      router.replace('/(tabs)/home');
      return;
    }

    // Cold start : on rédime le code nous-mêmes.
    void (async () => {
      try {
        const { data } = await apiClient.get<{ access_token?: string; token?: string }>(
          ENDPOINTS.auth.oauthExchange,
          { params: { exchange_code: exchangeCode } },
        );
        const accessToken = data?.access_token ?? data?.token;
        if (typeof accessToken !== 'string' || accessToken === '') {
          throw new Error('missing token');
        }
        setToken(accessToken);
        router.replace((consumePendingRoute() ?? '/(tabs)/home') as never);
      } catch {
        setFailed(true);
        Alert.alert(
          'Connexion interrompue',
          'La session de connexion a expiré. Veuillez vous reconnecter.',
        );
        router.replace('/(auth)/login');
      }
    })();
  }, [params.exchange_code, params.linked, params.link_error, isAuthenticated, setToken, router]);

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack
        flex={1}
        alignItems="center"
        justifyContent="center"
        gap={14}
        backgroundColor="$background"
      >
        <Spinner size="large" />
        <Paragraph fontSize={14} color="$slate500">
          {failed ? 'Redirection…' : 'Connexion en cours…'}
        </Paragraph>
      </YStack>
    </>
  );
}
