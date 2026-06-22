import NetInfo, { type NetInfoState } from '@react-native-community/netinfo';
import { CloudOff } from '@tamagui/lucide-icons';
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing } from 'react-native';
import { Paragraph, XStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

/**
 * Sticky offline banner. Subscribes to `NetInfo` and slides in from
 * the top when connectivity drops. The query cache still serves data
 * from disk (see `QueryProvider` → `PersistQueryClientProvider`), so
 * users keep browsing what they already loaded — we just signal that
 * fresh data isn't reachable right now.
 */
export function OfflineBanner() {
  const insets = useSafeAreaInsets();
  const [offline, setOffline] = useState(false);
  const translateY = useRef(new Animated.Value(-100)).current;

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state: NetInfoState) => {
      const isOffline =
        !state.isConnected || state.isInternetReachable === false;
      setOffline(isOffline);
      Animated.timing(translateY, {
        toValue: isOffline ? 0 : -100,
        duration: 240,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }).start();
    });
    return () => unsubscribe();
  }, [translateY]);

  return (
    <Animated.View
      pointerEvents={offline ? 'auto' : 'none'}
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        zIndex: 9999,
        transform: [{ translateY }],
        paddingTop: insets.top,
        backgroundColor: brand.slate900,
      }}
    >
      <XStack
        paddingHorizontal={16}
        paddingVertical={10}
        alignItems="center"
        gap={10}
      >
        <CloudOff size={16} color="white" />
        <Paragraph fontSize={13} fontWeight="700" color="white" flex={1}>
          Vous êtes hors ligne
        </Paragraph>
        <Paragraph fontSize={11} color="rgba(255,255,255,0.75)">
          Données mises en cache
        </Paragraph>
      </XStack>
    </Animated.View>
  );
}
