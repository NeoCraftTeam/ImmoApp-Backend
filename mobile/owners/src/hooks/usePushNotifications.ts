import Constants, { ExecutionEnvironment } from 'expo-constants';
import { useEffect, useRef } from 'react';
import { Platform } from 'react-native';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';
import { trackEvent } from '@/services/monitoring';
import { setRegisteredPushToken } from '@/services/push-token';

const IS_EXPO_GO =
  Constants.executionEnvironment === ExecutionEnvironment.StoreClient;

/**
 * Setup push notifications owner — copie du hook visitor avec
 * un channel Android dédié (couleur teal). Strict no-op en Expo Go
 * pour ne pas spam les warnings et ne pas crasher (SDK 53+ a retiré
 * la push delivery dans le client Expo Go).
 *
 * En dev/prod build :
 *   1. Demande permission une fois (au signIn)
 *   2. Récupère un Expo push token
 *   3. POST `/fcm/token` avec platform + provider
 *   4. Configure le notification channel Android
 *
 * Erreurs swallow — la push est best-effort, doit jamais bloquer l'app.
 */
export function usePushNotifications() {
  const { isAuthenticated, token } = useSession();
  const registeredRef = useRef(false);

  useEffect(() => {
    if (!isAuthenticated) {
      // Reset au signOut : le compte suivant sur le même appareil doit
      // ré-enregistrer SON token (le POST upsert réassigne le user_id).
      registeredRef.current = false;
      return;
    }
    if (registeredRef.current) return;
    if (IS_EXPO_GO) return;
    if (Constants.isDevice === false) return;
    registeredRef.current = true;

    (async () => {
      try {
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
            name: 'KeyHome Owner',
            importance: Notifications.AndroidImportance.DEFAULT,
            vibrationPattern: [0, 250, 250, 250],
            lightColor: '#0D9488',
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
        // Mémorisé pour le DELETE /fcm/token du signOut.
        setRegisteredPushToken(expoToken);
        trackEvent('owner.push.registered', { platform: Platform.OS });
      } catch {
        /* swallow — push best-effort */
      }
    })();
  }, [isAuthenticated, token]);
}
