import NetInfo, { type NetInfoState } from '@react-native-community/netinfo';
import { Cloud, CloudOff, RefreshCw } from '@tamagui/lucide-icons';
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

/**
 * Bandeau offline sticky en haut d'écran.
 *
 * UX :
 *   - **Glide-in soft** depuis le top quand NetInfo passe offline.
 *   - **Copy clair** : ce que l'utilisateur peut faire (continuer à
 *     consulter le cache) et ce qui est bloqué (favoris/messages).
 *   - **Bouton Réessayer** qui invalide les queries pour relancer
 *     les fetches dès que le réseau revient — l'utilisateur n'a pas
 *     besoin de redémarrer l'app.
 *   - **Toast de retour en ligne** : quand on repasse online, un
 *     bandeau vert « Connexion rétablie » s'affiche 1.6 s avant de
 *     se rétracter (confirmation tactile).
 *   - **Z-index 9999** + pointer-events conditionnel pour ne pas
 *     bloquer le contenu sous le bandeau quand il est caché.
 */
export function OfflineBanner() {
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const [state, setState] = useState<'online' | 'offline' | 'recovered'>('online');
  const translateY = useRef(new Animated.Value(-200)).current;
  const recoveredTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const wasOfflineRef = useRef(false);

  useEffect(() => {
    const animate = (toValue: number, duration = 280) => {
      Animated.timing(translateY, {
        toValue,
        duration,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }).start();
    };

    const unsubscribe = NetInfo.addEventListener((s: NetInfoState) => {
      const isOffline = !s.isConnected || s.isInternetReachable === false;

      if (isOffline) {
        wasOfflineRef.current = true;
        setState('offline');
        animate(0);
        return;
      }

      // Back online — montre le toast "recovered" 1.6 s seulement si
      // on a effectivement été offline (évite le flash au cold start).
      if (wasOfflineRef.current) {
        wasOfflineRef.current = false;
        setState('recovered');
        animate(0);
        if (recoveredTimer.current) clearTimeout(recoveredTimer.current);
        recoveredTimer.current = setTimeout(() => {
          animate(-200);
          setTimeout(() => setState('online'), 320);
        }, 1600);
      } else {
        setState('online');
        animate(-200, 0);
      }
    });

    return () => {
      unsubscribe();
      if (recoveredTimer.current) clearTimeout(recoveredTimer.current);
    };
  }, [translateY]);

  const isOffline = state === 'offline';
  const isRecovered = state === 'recovered';

  const handleRetry = () => {
    // Force refetch all active queries — la plupart capteront le
    // changement réseau au prochain focus, mais un retry explicite
    // donne un feedback immédiat.
    qc.invalidateQueries();
    qc.refetchQueries({ type: 'active' });
  };

  return (
    <Animated.View
      pointerEvents={state === 'online' ? 'none' : 'auto'}
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        zIndex: 9999,
        transform: [{ translateY }],
        paddingTop: insets.top,
        backgroundColor: isOffline ? brand.slate900 : brand.success,
        shadowColor: '#000',
        shadowOpacity: 0.12,
        shadowOffset: { width: 0, height: 4 },
        shadowRadius: 8,
        elevation: 8,
      }}
    >
      <XStack
        paddingHorizontal={14}
        paddingVertical={isOffline ? 12 : 10}
        alignItems="center"
        gap={10}
      >
        <YStack
          width={32}
          height={32}
          borderRadius={16}
          backgroundColor="rgba(255,255,255,0.15)"
          alignItems="center"
          justifyContent="center"
        >
          {isOffline ? (
            <CloudOff size={16} color="white" />
          ) : (
            <Cloud size={16} color="white" />
          )}
        </YStack>

        <YStack flex={1} gap={isRecovered ? 0 : 2}>
          <Paragraph fontSize={13.5} fontWeight="800" color="white">
            {isOffline ? 'Mode hors ligne' : 'Connexion rétablie'}
          </Paragraph>
          {isOffline ? (
            <Paragraph fontSize={11.5} color="rgba(255,255,255,0.8)">
              Vous consultez les annonces en cache. Favoris, recherches et messages
              se synchroniseront au retour du réseau.
            </Paragraph>
          ) : null}
        </YStack>

        {isOffline ? (
          <Pressable
            onPress={handleRetry}
            hitSlop={8}
            accessibilityRole="button"
            accessibilityLabel="Réessayer la connexion"
          >
            <XStack
              alignItems="center"
              gap={5}
              paddingHorizontal={10}
              paddingVertical={6}
              borderRadius={999}
              backgroundColor="rgba(255,255,255,0.15)"
            >
              <RefreshCw size={12} color="white" />
              <Paragraph fontSize={11.5} fontWeight="800" color="white">
                Réessayer
              </Paragraph>
            </XStack>
          </Pressable>
        ) : null}
      </XStack>
    </Animated.View>
  );
}
