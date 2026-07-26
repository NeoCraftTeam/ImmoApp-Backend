import { PortalProvider, TamaguiProvider, Theme } from 'tamagui';
import {
  Slot,
  SplashScreen,
  useGlobalSearchParams,
  usePathname,
  useRouter,
  useSegments,
} from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useColorScheme } from 'react-native';
import { useEffect, useState } from 'react';
import { SafeAreaProvider, initialWindowMetrics } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import * as WebBrowser from 'expo-web-browser';

import { OptionalClerkProvider } from '@/auth/OptionalClerkProvider';
import { consumePendingRoute, rememberPendingRoute } from '@/auth/pending-route';
import { SessionProvider, useSession } from '@/auth/SessionProvider';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { OfflineBanner } from '@/components/OfflineBanner';
import { SplashView } from '@/components/SplashView';
import { ToastHost } from '@/components/ToastHost';
import { useCreditsRealtime } from '@/hooks/useCreditsRealtime';
import { useMe } from '@/hooks/useMe';
import { usePushNotifications } from '@/hooks/usePushNotifications';
import { QueryProvider } from '@/providers/QueryProvider';
import { initMonitoring, reportError } from '@/services/monitoring';
import config from '../tamagui.config';

import '@/i18n'; // side-effect: initialise locale before any screen renders

initMonitoring();

// Required for Clerk / OAuth browser sessions on Android.
WebBrowser.maybeCompleteAuthSession();

/**
 * Root layout — provider stack for the whole owner app:
 *
 *   GestureHandlerRoot → SafeAreaProvider → TamaguiProvider → Theme
 *     → QueryProvider → SessionProvider → AuthGate + OfflineBanner +
 *       <Slot /> + SplashView
 *
 * Unlike the visitor app, the owner app is auth-required: `AuthGate`
 * redirects any unauthenticated navigation (except the onboarding +
 * (auth) groups) to the login screen.
 */
SplashScreen.preventAutoHideAsync().catch(() => {});

export default function RootLayout() {
  const scheme = useColorScheme();

  useEffect(() => {
    const t = setTimeout(() => {
      SplashScreen.hideAsync().catch(() => {});
    }, 100);
    return () => clearTimeout(t);
  }, []);

  return (
    <ErrorBoundary onError={(err) => reportError(err)}>
      <GestureHandlerRootView style={{ flex: 1 }}>
        <SafeAreaProvider initialMetrics={initialWindowMetrics}>
          <TamaguiProvider config={config}>
            <PortalProvider shouldAddRootHost>
              <Theme name={scheme === 'dark' ? 'dark' : 'light'}>
                <QueryProvider>
                  <OptionalClerkProvider>
                    <SessionProvider>
                      <AuthGate />
                      <OfflineBanner />
                      <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
                      <Slot />
                      <ToastHost />
                      <SplashGate />
                    </SessionProvider>
                  </OptionalClerkProvider>
                </QueryProvider>
              </Theme>
            </PortalProvider>
          </TamaguiProvider>
        </SafeAreaProvider>
      </GestureHandlerRootView>
    </ErrorBoundary>
  );
}

/**
 * Redirects based on auth state. Runs after the session hydrates so we
 * never bounce a logged-in owner to the login screen on cold start.
 */
/**
 * Écrans du groupe (auth) qui gèrent EUX-MÊMES leur navigation après
 * authentification — l'AuthGate ne doit pas les bouncer :
 *  - `verify-otp` : après une inscription email, le token est installé
 *    mais tout le reste de l'API répond 403 EMAIL_UNVERIFIED tant que
 *    l'OTP n'est pas confirmé — bouncer vers le dashboard rendrait la
 *    vérification impossible.
 *  - `login` / `register` : ils naviguent seuls en fin de flux (succès,
 *    verify-otp…) ; les bouncer en parallèle crée une course de
 *    navigation qui écrase leur destination.
 */
const SELF_ROUTING_AUTH_SCREENS = new Set(['verify-otp', 'login', 'register']);

function AuthGate() {
  const { isAuthenticated, isLoading } = useSession();
  const segments = useSegments();
  const pathname = usePathname();
  const params = useGlobalSearchParams<Record<string, string | string[]>>();
  const router = useRouter();

  // Push notifications — registers FCM token at signed-in mount.
  usePushNotifications();
  // Crédits en temps réel : solde + transactions mis à jour via le canal
  // privé user.{id} (achat crédité, boost dépensé…), sans polling.
  useCreditsRealtime(useMe(isAuthenticated).data?.id);

  useEffect(() => {
    if (isLoading) return;
    const root = segments[0] as string | undefined;
    const authScreen = segments[1] as string | undefined;
    const inAuth = root === '(auth)';
    const inOnboarding = root === 'onboarding';
    const inGate = root === undefined;

    if (!isAuthenticated && !inAuth && !inOnboarding && !inGate) {
      // Deep-link entrant (paiement, notification…) : on mémorise la
      // destination pour la rejouer après connexion.
      const query = Object.entries(params)
        .map(([k, v]) => {
          const value = Array.isArray(v) ? v[0] : v;
          return value === undefined
            ? null
            : `${encodeURIComponent(k)}=${encodeURIComponent(value)}`;
        })
        .filter((pair): pair is string => pair !== null)
        .join('&');
      rememberPendingRoute(query ? `${pathname}?${query}` : pathname);
      router.replace('/(auth)/login');
    } else if (isAuthenticated && inAuth && !SELF_ROUTING_AUTH_SCREENS.has(authScreen ?? '')) {
      router.replace((consumePendingRoute() ?? '/(tabs)/dashboard') as never);
    }
  }, [isAuthenticated, isLoading, segments, pathname, params, router]);

  return null;
}

function SplashGate() {
  const { isLoading } = useSession();
  const [minDelayElapsed, setMinDelayElapsed] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => setMinDelayElapsed(true), 600);
    return () => clearTimeout(t);
  }, []);

  return <SplashView ready={!isLoading && minDelayElapsed} />;
}
