import { useEffect, useRef } from 'react';
import { Animated, Easing } from 'react-native';
import { YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

/**
 * Loading placeholder for an AdCard. Two-tone shimmer driven by RN
 * Animated with `useNativeDriver: true` so it costs near-zero JS-thread
 * overhead even when 8 skeletons run simultaneously during the initial
 * feed load.
 *
 * Mirrors AdCard's geometry exactly (3:2 hero + 3-line text block) so
 * the layout doesn't jump when real cards mount.
 */
export function AdCardSkeleton() {
  const pulse = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, {
          toValue: 1,
          duration: 700,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.timing(pulse, {
          toValue: 0,
          duration: 700,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [pulse]);

  const opacity = pulse.interpolate({ inputRange: [0, 1], outputRange: [0.6, 1] });

  const Bar = ({ width, height = 12 }: { width: number | string; height?: number }) => (
    <Animated.View
      style={{
        width: width as number,
        height,
        borderRadius: 6,
        backgroundColor: brand.slate100,
        opacity,
      }}
    />
  );

  return (
    <YStack gap={10}>
      <Animated.View
        style={{
          width: '100%',
          aspectRatio: 3 / 2,
          borderRadius: 14,
          backgroundColor: brand.slate100,
          opacity,
        }}
      />
      <YStack gap={6} paddingHorizontal={2}>
        <Bar width="80%" height={14} />
        <Bar width="55%" height={11} />
        <Bar width="40%" height={11} />
      </YStack>
    </YStack>
  );
}
