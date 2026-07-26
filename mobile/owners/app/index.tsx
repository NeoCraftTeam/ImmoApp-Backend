import { Redirect } from 'expo-router';
import { useEffect, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { useSession } from '@/auth/SessionProvider';
import { ONBOARDING_DONE_KEY } from '@/auth/storage-keys';

/**
 * Route gate — figures out where to send a freshly-launched owner app.
 *
 *  - First-ever launch       → `/onboarding`
 *  - Returning, signed-in    → `/(tabs)/dashboard`
 *  - Returning, signed-out   → `/(auth)/login`
 */
export default function Index() {
  const { isAuthenticated, isLoading } = useSession();
  const [onboardingDone, setOnboardingDone] = useState<boolean | null>(null);

  useEffect(() => {
    (async () => {
      try {
        let done: string | null = null;
        if (Platform.OS === 'web') {
          done =
            typeof window !== 'undefined'
              ? window.localStorage?.getItem(ONBOARDING_DONE_KEY) ?? null
              : null;
        } else {
          done = await SecureStore.getItemAsync(ONBOARDING_DONE_KEY);
        }
        setOnboardingDone(done === '1');
      } catch {
        setOnboardingDone(false);
      }
    })();
  }, []);

  if (isLoading || onboardingDone === null) return null;
  if (!onboardingDone) return <Redirect href="/onboarding" />;
  return <Redirect href={isAuthenticated ? '/(tabs)/dashboard' : '/(auth)/login'} />;
}
