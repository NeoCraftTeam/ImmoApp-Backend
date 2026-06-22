import { PortalProvider, TamaguiProvider, Theme } from 'tamagui';
import { Slot, SplashScreen } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useColorScheme } from 'react-native';
import { useEffect, useState } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';

import { SessionProvider, useSession } from '@/auth/SessionProvider';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { OfflineBanner } from '@/components/OfflineBanner';
import { SplashView } from '@/components/SplashView';
import { usePushNotifications } from '@/hooks/usePushNotifications';
import { CompareProvider } from '@/providers/CompareProvider';
import { QueryProvider } from '@/providers/QueryProvider';
import { initMonitoring, reportError } from '@/services/monitoring';
import config from '../tamagui.config';

// Initialise Sentry au plus tôt — avant que React mount.
// No-op silencieux en Expo Go (pas de native module) et sans DSN.
initMonitoring();

import '@/i18n'; // side-effect import: initialises locale before any screen renders

/**
 * Root layout — provider stack pour toute l'app :
 *
 *   GestureHandlerRoot     gestures & drags (react-native-gesture-handler)
 *     SafeAreaProvider     insets notch / home-indicator
 *       TamaguiProvider    design tokens + extraction CSS
 *         Theme            light/dark dynamique
 *           QueryProvider  TanStack Query + persistance AsyncStorage
 *             SessionProvider  token Sanctum bearer (SecureStore)
 *               CompareProvider compareur d'annonces (persisted)
 *                 PushBridge   register expo-push token
 *                 OfflineBanner sticky banner offline (NetInfo)
 *                 SplashView   overlay Lottie/wordmark pendant la rehydrate
 *                 <Slot />     route active
 *
 *  Le splash natif est masqué dès que React mount ; on prend le relais
 *  avec `SplashView` qui reste visible jusqu'à ce que la session ait
 *  fini de lire SecureStore (évite un flash blanc + saute proprement
 *  vers la première route — home ou onboarding).
 */
SplashScreen.preventAutoHideAsync().catch(() => {});

export default function RootLayout() {
  const scheme = useColorScheme();

  useEffect(() => {
    // Cache le splash natif — on bascule sur notre SplashView animé qui
    // tient jusqu'à ce que SessionProvider ait fini de réhydrater.
    const t = setTimeout(() => {
      SplashScreen.hideAsync().catch(() => {});
    }, 100);
    return () => clearTimeout(t);
  }, []);

  return (
    <ErrorBoundary onError={reportError}>
      <GestureHandlerRootView style={{ flex: 1 }}>
        <SafeAreaProvider>
          <TamaguiProvider config={config}>
            <Theme name={scheme === 'dark' ? 'dark' : 'light'}>
              <PortalProvider shouldAddRootHost>
                <QueryProvider>
                  <SessionProvider>
                    <CompareProvider>
                      <PushNotificationsBridge />
                      <OfflineBanner />
                      <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
                      <Slot />
                      <SplashGate />
                    </CompareProvider>
                  </SessionProvider>
                </QueryProvider>
              </PortalProvider>
            </Theme>
          </TamaguiProvider>
        </SafeAreaProvider>
      </GestureHandlerRootView>
    </ErrorBoundary>
  );
}

/**
 * Lit `useSession().isLoading` puis cache le splash overlay. Le boot
 * complet dure typiquement < 400 ms : on n'attend donc qu'un tick puis
 * fade-out (la SplashView gère elle-même l'animation de sortie).
 */
function SplashGate() {
  const { isLoading } = useSession();
  const [minDelayElapsed, setMinDelayElapsed] = useState(false);

  useEffect(() => {
    // Hold le splash au moins 600 ms même si la session se rehydrate
    // instantanément — donne le temps à l'animation de jouer son intro.
    const t = setTimeout(() => setMinDelayElapsed(true), 600);
    return () => clearTimeout(t);
  }, []);

  return <SplashView ready={!isLoading && minDelayElapsed} />;
}

/**
 * Tiny bridge component pour que `usePushNotifications` tourne à
 * l'intérieur du SessionProvider (il consomme `useSession`). Pur
 * side-effect, ne rend rien.
 */
function PushNotificationsBridge() {
  usePushNotifications();
  return null;
}
