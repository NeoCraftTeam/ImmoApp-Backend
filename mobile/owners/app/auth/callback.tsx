import { useRouter } from 'expo-router';
import { useEffect } from 'react';
import { Spinner, YStack } from 'tamagui';

/**
 * Deep-link landing for OAuth return URLs (`keyhomeowners://auth/callback`).
 * The actual token exchange runs inside `startSSOFlow` /
 * `WebBrowser.openAuthSessionAsync`; this screen only covers cold starts
 * where the OS opens the app directly on the callback URL.
 */
export default function AuthCallbackScreen() {
  const router = useRouter();

  useEffect(() => {
    const timer = setTimeout(() => {
      router.replace('/(auth)/login');
    }, 1500);

    return () => clearTimeout(timer);
  }, [router]);

  return (
    <YStack flex={1} alignItems="center" justifyContent="center" backgroundColor="$background">
      <Spinner size="large" />
    </YStack>
  );
}
