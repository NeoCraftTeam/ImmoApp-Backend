import Constants, { ExecutionEnvironment } from 'expo-constants';
import { useEffect, useRef } from 'react';
import { Platform } from 'react-native';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';

/**
 * Expo Go on SDK 53+ stripped remote-push delivery for both iOS and
 * Android. Importing `expo-notifications` in that environment spams
 * three deprecation warnings on every cold start. We detect Expo Go
 * with `executionEnvironment === 'storeClient'` and skip the whole
 * module — including the static import, since just *importing* the
 * package triggers the warnings.
 */
const IS_EXPO_GO =
  Constants.executionEnvironment === ExecutionEnvironment.StoreClient;

/**
 * Push notifications setup — runs once at session-authenticated mount.
 * Registers the device with Expo's push service, exchanges the token
 * with the backend `POST /fcm/token`, and lets the OS deep-link the
 * user back to the right screen via the notification payload.
 *
 * In Expo Go this is a strict no-op: we never load `expo-notifications`
 * (lazy-loaded behind the env check), so no deprecation warnings, no
 * native crashes. In a development or production build, push works
 * exactly as documented.
 */
export function usePushNotifications() {
  const { isAuthenticated, token } = useSession();
  const registeredRef = useRef(false);

  useEffect(() => {
    if (!isAuthenticated || registeredRef.current) {
      return;
    }
    if (IS_EXPO_GO) {
      // Expo Go SDK 53+ — push isn't supported and importing the module
      // spams warnings. Skip silently; dev builds re-enable everything.
      return;
    }
    if (Constants.isDevice === false) {
      // Simulator can't receive push notifications. Skip silently.
      return;
    }
    registeredRef.current = true;

    (async () => {
      try {
        // Lazy-load so Expo Go bundles never evaluate the module.
        const Notifications: typeof import('expo-notifications') =
          await import('expo-notifications');

        Notifications.setNotificationHandler({
          handleNotification: async () => ({
            shouldShowBanner: true,
            shouldShowList: true,
            shouldPlaySound: true,
            shouldSetBadge: true,
          }),
        });

        let { status } = await Notifications.getPermissionsAsync();
        if (status !== 'granted') {
          const ask = await Notifications.requestPermissionsAsync();
          status = ask.status;
        }
        if (status !== 'granted') return;

        if (Platform.OS === 'android') {
          await Notifications.setNotificationChannelAsync('default', {
            name: 'KeyHome',
            importance: Notifications.AndroidImportance.DEFAULT,
            vibrationPattern: [0, 250, 250, 250],
          });
        }

        const projectId =
          Constants.expoConfig?.extra?.eas?.projectId ??
          Constants.easConfig?.projectId;
        const tokenResponse = projectId
          ? await Notifications.getExpoPushTokenAsync({ projectId })
          : await Notifications.getExpoPushTokenAsync();

        const expoToken = tokenResponse.data;
        if (!expoToken) return;

        await apiClient.post(ENDPOINTS.notifications.fcmToken, {
          token: expoToken,
          platform: Platform.OS,
          provider: 'expo',
        });
      } catch {
        /* swallow — push is best-effort and shouldn't break the app */
      }
    })();
  }, [isAuthenticated, token]);
}
