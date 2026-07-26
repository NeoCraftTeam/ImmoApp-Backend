import { Redirect } from 'expo-router';
import { useEffect, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { ONBOARDING_DONE_KEY, PERMISSIONS_PRIMED_KEY } from '@/auth/storage-keys';

/**
 * Route gate — figures out where to send a freshly-launched app.
 *
 *  - First-ever launch      →  `/onboarding`   (welcome carousel)
 *  - Permissions not primed →  `/permissions`  (location + notifications,
 *                              une seule fois — couvre aussi les installs
 *                              existantes qui ont déjà vu l'onboarding)
 *  - Returning visitor      →  `/home`         (browse feed, anonymous OK)
 *
 * The gate runs once per cold-start; subsequent launches read the flags
 * from SecureStore and skip straight to the feed. We DON'T gate browse
 * access on auth — visitors can read the feed without an account;
 * sign-in is only required for favorites / contact / unlock-points
 * actions, which prompt at the point of use.
 */
export default function Index() {
  const [target, setTarget] = useState<'/onboarding' | '/permissions' | '/(tabs)/home' | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const read = async (key: string): Promise<string | null> => {
          if (Platform.OS === 'web') {
            return typeof window !== 'undefined'
              ? (window.localStorage?.getItem(key) ?? null)
              : null;
          }
          return SecureStore.getItemAsync(key);
        };
        const onboardingDone = (await read(ONBOARDING_DONE_KEY)) === '1';
        if (!onboardingDone) {
          setTarget('/onboarding');
          return;
        }
        const permissionsPrimed = (await read(PERMISSIONS_PRIMED_KEY)) === '1';
        setTarget(permissionsPrimed ? '/(tabs)/home' : '/permissions');
      } catch {
        setTarget('/onboarding');
      }
    })();
  }, []);

  if (!target) return null;
  return <Redirect href={target} />;
}
