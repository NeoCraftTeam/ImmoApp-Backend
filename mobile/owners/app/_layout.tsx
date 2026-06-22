import { PortalProvider, TamaguiProvider, Theme } from 'tamagui';
import { Slot, SplashScreen, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useColorScheme } from 'react-native';
import { useEffect, useState } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';

import { SessionProvider, useSession } from '@/auth/SessionProvider';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { OfflineBanner } from '@/components/OfflineBanner';
import { SplashView } from '@/components/SplashView';
import { QueryProvider } from '@/providers/QueryProvider';
import { initMonitoring, reportError } from '@/services/monitoring';
import config from '../tamagui.config';

import '@/i18n'; // side-effect: initialise locale before any screen renders

initMonitoring();

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
        <SafeAreaProvider>
          <TamaguiProvider config={config}>
            <PortalProvider shouldAddRootHost>
              <Theme name={scheme === 'dark' ? 'dark' : 'light'}>
                <QueryProvider>
                  <SessionProvider>
                    <AuthGate />
                    <OfflineBanner />
                    <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
                    <Slot />
                    <SplashGate />
                  </SessionProvider>
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
function AuthGate() {
  const { isAuthenticated, isLoading } = useSession();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    if (isLoading) return;
    const root = segments[0] as string | undefined;
    const inAuth = root === '(auth)';
    const inOnboarding = root === 'onboarding';
    const inGate = root === undefined;

    if (!isAuthenticated && !inAuth && !inOnboarding && !inGate) {
      router.replace('/(auth)/login');
    } else if (isAuthenticated && inAuth) {
      router.replace('/(tabs)/dashboard');
    }
  }, [isAuthenticated, isLoading, segments, router]);

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
