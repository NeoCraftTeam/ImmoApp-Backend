import { Redirect } from 'expo-router';
import { useEffect, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { ONBOARDING_DONE_KEY } from '@/auth/storage-keys';

/**
 * Route gate — figures out where to send a freshly-launched app.
 *
 *  - First-ever launch  →  `/onboarding`  (welcome carousel)
 *  - Returning visitor  →  `/home`        (browse feed, anonymous OK)
 *
 * The gate runs once per cold-start; subsequent launches read the
 * `keyhome.onboarding.done` flag from SecureStore and skip straight
 * to the feed. We DON'T gate browse access on auth — visitors can
 * read the feed without an account; sign-in is only required for
 * favorites / contact / unlock-points actions, which prompt at the
 * point of use.
 */
export default function Index() {
  const [target, setTarget] = useState<'/onboarding' | '/home' | null>(null);

  useEffect(() => {
    (async () => {
      try {
        let done: string | null = null;
        if (Platform.OS === 'web') {
          done = typeof window !== 'undefined'
            ? window.localStorage?.getItem(ONBOARDING_DONE_KEY) ?? null
            : null;
        } else {
          done = await SecureStore.getItemAsync(ONBOARDING_DONE_KEY);
        }
        setTarget(done === '1' ? '/home' : '/onboarding');
      } catch {
        setTarget('/onboarding');
      }
    })();
  }, []);

  if (!target) return null;
  return <Redirect href={target} />;
}
