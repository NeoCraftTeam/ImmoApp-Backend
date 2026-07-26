import { Bell, MapPin } from '@tamagui/lucide-icons';
import * as Location from 'expo-location';
import * as Notifications from 'expo-notifications';
import { useRouter } from 'expo-router';
import * as SecureStore from 'expo-secure-store';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Platform, Pressable } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { PERMISSIONS_PRIMED_KEY } from '@/auth/storage-keys';
import { currencyStore } from '@/services/currency';
import { brand } from '@/theme/tokens';

/**
 * Priming des autorisations — affiché UNE fois, juste après l'onboarding
 * (ou au premier lancement post-mise-à-jour pour les installs existantes).
 *
 * Pourquoi un écran dédié plutôt que des prompts au fil de l'eau : on
 * explique la valeur AVANT le dialogue système (pattern « permission
 * priming », recommandé par les HIG Apple) — le taux d'acceptation est
 * bien meilleur, et la localisation accordée ici permet immédiatement
 * d'afficher les prix dans la devise locale (GPS → pays → devise) et les
 * annonces à proximité. Refuser n'est jamais bloquant : « Plus tard »
 * continue vers l'app, les features redemanderont à l'usage.
 */
export default function PermissionsPriming() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [requesting, setRequesting] = useState(false);

  const persistDone = useCallback(async () => {
    try {
      if (Platform.OS === 'web') {
        window.localStorage?.setItem(PERMISSIONS_PRIMED_KEY, '1');
      } else {
        await SecureStore.setItemAsync(PERMISSIONS_PRIMED_KEY, '1');
      }
    } catch {
      /* non-fatal — l'écran réapparaîtra au prochain lancement */
    }
  }, []);

  const finish = useCallback(async () => {
    await persistDone();
    router.replace('/(tabs)/home');
  }, [persistDone, router]);

  const handleAllowAll = useCallback(async () => {
    setRequesting(true);
    try {
      const location = await Location.requestForegroundPermissionsAsync();
      if (location.granted) {
        // La devise peut maintenant se résoudre par GPS (pays réel de
        // l'appareil) même si l'API géo est injoignable.
        currencyStore.redetect();
      }
    } catch {
      /* simulateur / permission indisponible — on continue */
    }
    try {
      await Notifications.requestPermissionsAsync();
    } catch {
      /* Expo Go : les push distants sont indisponibles — non bloquant */
    }
    setRequesting(false);
    await finish();
  }, [finish]);

  return (
    <YStack
      flex={1}
      backgroundColor="$background"
      paddingTop={insets.top + 28}
      paddingBottom={insets.bottom + 20}
      paddingHorizontal="$5"
    >
      <YStack flex={1} gap={28}>
        <YStack gap={10} marginTop={12}>
          <H2 fontSize={28} fontWeight="900" letterSpacing={-0.5} lineHeight={34}>
            Avant de commencer
          </H2>
          <Paragraph fontSize={14.5} color="$slate500" lineHeight={21}>
            Deux autorisations rendent KeyHome vraiment utile. Vous pouvez les
            modifier à tout moment dans les réglages.
          </Paragraph>
        </YStack>

        <YStack gap={18}>
          <XStack gap={14} alignItems="flex-start">
            <YStack
              width={44}
              height={44}
              borderRadius={14}
              alignItems="center"
              justifyContent="center"
              backgroundColor="$brandAlpha10"
            >
              <MapPin size={22} color={brand.primary} />
            </YStack>
            <YStack flex={1} gap={3}>
              <Paragraph fontSize={15.5} fontWeight="800" color="$slate900">
                Localisation
              </Paragraph>
              <Paragraph fontSize={13.5} color="$slate500" lineHeight={19}>
                Prix affichés dans votre devise locale et annonces autour de
                vous. Jamais partagée avec les annonceurs.
              </Paragraph>
            </YStack>
          </XStack>

          <XStack gap={14} alignItems="flex-start">
            <YStack
              width={44}
              height={44}
              borderRadius={14}
              alignItems="center"
              justifyContent="center"
              backgroundColor="$brandAlpha10"
            >
              <Bell size={22} color={brand.primary} />
            </YStack>
            <YStack flex={1} gap={3}>
              <Paragraph fontSize={15.5} fontWeight="800" color="$slate900">
                Notifications
              </Paragraph>
              <Paragraph fontSize={13.5} color="$slate500" lineHeight={19}>
                Réponses des bailleurs et alertes quand une annonce correspond
                à vos critères. Pas de spam.
              </Paragraph>
            </YStack>
          </XStack>
        </YStack>
      </YStack>

      <YStack gap={12}>
        <Pressable
          onPress={handleAllowAll}
          disabled={requesting}
          accessibilityRole="button"
          accessibilityLabel="Autoriser la localisation et les notifications"
        >
          <XStack
            height={52}
            borderRadius={16}
            alignItems="center"
            justifyContent="center"
            backgroundColor={brand.primary}
            opacity={requesting ? 0.7 : 1}
          >
            {requesting ? (
              <ActivityIndicator color="white" />
            ) : (
              <Paragraph fontSize={16} fontWeight="800" color="white">
                Autoriser
              </Paragraph>
            )}
          </XStack>
        </Pressable>
        <Pressable
          onPress={finish}
          disabled={requesting}
          accessibilityRole="button"
          accessibilityLabel="Continuer sans autoriser"
        >
          <XStack height={44} alignItems="center" justifyContent="center">
            <Paragraph fontSize={14.5} fontWeight="700" color="$slate500">
              Plus tard
            </Paragraph>
          </XStack>
        </Pressable>
      </YStack>
    </YStack>
  );
}
