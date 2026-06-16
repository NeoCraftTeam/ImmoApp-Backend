import { TamaguiProvider, Theme } from 'tamagui';
import { Slot, SplashScreen } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useColorScheme } from 'react-native';
import { useEffect } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';

import { SessionProvider } from '@/auth/SessionProvider';
import { CompareProvider } from '@/providers/CompareProvider';
import { QueryProvider } from '@/providers/QueryProvider';
import config from '../tamagui.config';

import '@/i18n'; // side-effect import: initialises locale before any screen renders

/**
 * Root layout — wraps every route in the standard provider stack:
 *
 *   GestureHandlerRoot   ← required for any drag / swipe handler downstream
 *     SafeAreaProvider   ← gives `useSafeAreaInsets()` to children for notch / home-indicator padding
 *       TamaguiProvider  ← cross-platform UI tokens + themes
 *         Theme          ← runtime light/dark switch tracking system preference
 *           QueryProvider← TanStack Query client
 *             SessionProvider ← persisted bearer token + sign-in/out helpers
 *               <Slot />  ← the routed screen
 *
 * `SplashScreen.preventAutoHideAsync()` keeps the native splash visible
 * until the session has hydrated from SecureStore, then we hide it so
 * the user never sees a half-painted gate redirect.
 */
SplashScreen.preventAutoHideAsync().catch(() => {});

export default function RootLayout() {
  const scheme = useColorScheme();

  useEffect(() => {
    // Best-effort hide on mount — the actual hide-after-hydrate logic
    // could live in a child component watching `useSession().isLoading`,
    // but for the visitor flow (which is browsable anonymously) we
    // accept a small flash over making the splash chain more complex.
    const t = setTimeout(() => {
      SplashScreen.hideAsync().catch(() => {});
    }, 250);
    return () => clearTimeout(t);
  }, []);

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <TamaguiProvider config={config}>
          <Theme name={scheme === 'dark' ? 'dark' : 'light'}>
            <QueryProvider>
              <SessionProvider>
                <CompareProvider>
                  <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
                  <Slot />
                </CompareProvider>
              </SessionProvider>
            </QueryProvider>
          </Theme>
        </TamaguiProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
