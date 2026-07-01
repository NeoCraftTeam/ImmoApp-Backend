import NetInfo, { type NetInfoState } from '@react-native-community/netinfo';
import { CheckCircle2, CloudOff, RefreshCw } from '@tamagui/lucide-icons';
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Pressable } from 'react-native';
import { Paragraph, XStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

/**
 * Indicateur réseau compact — une **pill flottante** centrée sous la
 * safe-area, plutôt qu'un bandeau pleine largeur qui écrasait tout le
 * haut de l'écran.
 *
 *   - Hors ligne : pill sombre « Hors ligne · Réessayer » (tap = refetch).
 *   - Retour en ligne : pill verte « Connexion rétablie », auto-masquée
 *     après 1.6 s.
 *   - Slide + fade depuis le haut, native driver, z-index élevé.
 */
export function OfflineBanner() {
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const [state, setState] = useState<'online' | 'offline' | 'recovered'>('online');
  const translateY = useRef(new Animated.Value(-80)).current;
  const opacity = useRef(new Animated.Value(0)).current;
  const recoveredTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const wasOfflineRef = useRef(false);

  useEffect(() => {
    const animate = (toY: number, toOpacity: number, duration = 260) => {
      Animated.parallel([
        Animated.timing(translateY, {
          toValue: toY,
          duration,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(opacity, {
          toValue: toOpacity,
          duration,
          useNativeDriver: true,
        }),
      ]).start();
    };

    const unsubscribe = NetInfo.addEventListener((s: NetInfoState) => {
      const isOffline = !s.isConnected || s.isInternetReachable === false;

      if (isOffline) {
        wasOfflineRef.current = true;
        setState('offline');
        animate(0, 1);
        return;
      }

      if (wasOfflineRef.current) {
        wasOfflineRef.current = false;
        setState('recovered');
        animate(0, 1);
        if (recoveredTimer.current) clearTimeout(recoveredTimer.current);
        recoveredTimer.current = setTimeout(() => {
          animate(-80, 0);
          setTimeout(() => setState('online'), 300);
        }, 1600);
      } else {
        setState('online');
        animate(-80, 0, 0);
      }
    });

    return () => {
      unsubscribe();
      if (recoveredTimer.current) clearTimeout(recoveredTimer.current);
    };
  }, [translateY, opacity]);

  const isOffline = state === 'offline';

  const handleRetry = () => {
    qc.invalidateQueries();
    qc.refetchQueries({ type: 'active' });
  };

  return (
    <Animated.View
      pointerEvents={state === 'online' ? 'none' : 'box-none'}
      style={{
        position: 'absolute',
        top: insets.top + 8,
        left: 0,
        right: 0,
        zIndex: 9999,
        alignItems: 'center',
        transform: [{ translateY }],
        opacity,
      }}
    >
      <Pressable
        onPress={isOffline ? handleRetry : undefined}
        accessibilityRole={isOffline ? 'button' : 'text'}
        accessibilityLabel={isOffline ? 'Hors ligne — appuyer pour réessayer' : 'Connexion rétablie'}
      >
        <XStack
          alignItems="center"
          gap={7}
          paddingHorizontal={14}
          paddingVertical={8}
          borderRadius={999}
          backgroundColor={isOffline ? brand.slate900 : brand.success}
          shadowColor="#000"
          shadowOpacity={0.18}
          shadowOffset={{ width: 0, height: 3 }}
          shadowRadius={8}
          elevation={6}
        >
          {isOffline ? (
            <CloudOff size={14} color="white" />
          ) : (
            <CheckCircle2 size={14} color="white" />
          )}
          <Paragraph fontSize={13} fontWeight="700" color="white">
            {isOffline ? 'Hors ligne' : 'Connexion rétablie'}
          </Paragraph>
          {isOffline ? (
            <>
              <Paragraph fontSize={13} color="rgba(255,255,255,0.4)">
                ·
              </Paragraph>
              <RefreshCw size={12} color="rgba(255,255,255,0.85)" />
              <Paragraph fontSize={12.5} fontWeight="700" color="rgba(255,255,255,0.85)">
                Réessayer
              </Paragraph>
            </>
          ) : null}
        </XStack>
      </Pressable>
    </Animated.View>
  );
}
