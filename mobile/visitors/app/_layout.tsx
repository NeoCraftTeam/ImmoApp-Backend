import { PortalProvider, TamaguiProvider, Theme } from 'tamagui';
import { Stack, SplashScreen } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { SafeAreaProvider, initialWindowMetrics } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';

import { SessionProvider, useSession } from '@/auth/SessionProvider';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { OfflineBanner } from '@/components/OfflineBanner';
import { SplashView } from '@/components/SplashView';
import { usePushNotifications } from '@/hooks/usePushNotifications';
import { CompareProvider } from '@/providers/CompareProvider';
import { QueryProvider } from '@/providers/QueryProvider';
import { ThemeProvider, useAppTheme } from '@/providers/ThemeProvider';
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
 *       ThemeProvider      mode d'apparence persisté (system/light/dark)
 *         TamaguiProvider  design tokens + extraction CSS
 *           Theme          light/dark résolu par ThemeProvider
 *             QueryProvider  TanStack Query + persistance AsyncStorage
 *               SessionProvider  token Sanctum bearer (SecureStore)
 *                 CompareProvider compareur d'annonces (persisted)
 *                   OfflineBanner sticky banner offline (NetInfo)
 *                   <Stack />     pile de navigation (swipe-back iOS actif)
 *                   SplashView    overlay pendant la rehydrate
 *
 *  Le splash natif est masqué dès que React mount ; on prend le relais
 *  avec `SplashView` qui reste visible jusqu'à ce que la session ait
 *  fini de lire SecureStore (évite un flash blanc + saute proprement
 *  vers la première route — home ou onboarding).
 */
SplashScreen.preventAutoHideAsync().catch(() => {});

export default function RootLayout() {
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
        <SafeAreaProvider initialMetrics={initialWindowMetrics}>
          <ThemeProvider>
            <TamaguiProvider config={config}>
              <ThemedRoot />
            </TamaguiProvider>
          </ThemeProvider>
        </SafeAreaProvider>
      </GestureHandlerRootView>
    </ErrorBoundary>
  );
}

/**
 * Applique le schéma résolu par `ThemeProvider` à Tamagui + la StatusBar,
 * puis monte la pile de navigation. `gestureEnabled` active le swipe-back
 * iOS depuis le bord gauche sur tous les écrans de la pile (le root passait
 * auparavant par `<Slot/>`, sans navigateur, donc sans geste retour).
 */
function ThemedRoot() {
  const { scheme } = useAppTheme();

  return (
    <Theme name={scheme}>
      <PortalProvider shouldAddRootHost>
        <QueryProvider>
          <SessionProvider>
            <CompareProvider>
              <PushNotificationsBridge />
              <OfflineBanner />
              <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
              <Stack
                screenOptions={{
                  headerShown: false,
                  gestureEnabled: true,
                  animation: 'slide_from_right',
                }}
              />
              <SplashGate />
            </CompareProvider>
          </SessionProvider>
        </QueryProvider>
      </PortalProvider>
    </Theme>
  );
}

/**
 * Lit `useSession().isLoading` puis cache le splash overlay une fois le
 * min-delay de 3 s écoulé (la SplashView gère elle-même l'animation de
 * sortie).
 */
function SplashGate() {
  const { isLoading } = useSession();
  const [minDelayElapsed, setMinDelayElapsed] = useState(false);

  useEffect(() => {
    // Hold le splash 3000 ms minimum même si la session se rehydrate
    // instantanément — demande produit : le splash doit rester visible
    // au moins 3 secondes, le temps que l'anim logo + signature joue.
    const t = setTimeout(() => setMinDelayElapsed(true), 3000);
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
