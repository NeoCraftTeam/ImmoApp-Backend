import { useEffect, useRef, type ReactNode } from 'react';
import { Animated, Easing, type ViewStyle } from 'react-native';

import { useReducedMotion } from '@/hooks/useReducedMotion';

/**
 * Wrapper de fade + slide-up natif (RN Animated, useNativeDriver). Pas
 * de dépendance Reanimated. Sert pour stagger les cartes de listes et
 * adoucir les entrées d'écran sans coûter en perf.
 */
export function FadeIn({
  children,
  delay = 0,
  distance = 8,
  style,
}: {
  children: ReactNode;
  delay?: number;
  distance?: number;
  style?: ViewStyle;
}) {
  const reducedMotion = useReducedMotion();
  const opacity = useRef(new Animated.Value(0)).current;
  const translate = useRef(new Animated.Value(distance)).current;

  useEffect(() => {
    // Reduced motion : cross-fade court sans slide.
    if (reducedMotion) {
      translate.setValue(0);
      Animated.timing(opacity, {
        toValue: 1,
        duration: 160,
        delay: 0,
        useNativeDriver: true,
      }).start();
      return;
    }

    Animated.parallel([
      Animated.timing(opacity, {
        toValue: 1,
        duration: 320,
        delay,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(translate, {
        toValue: 0,
        duration: 320,
        delay,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start();
  }, [delay, opacity, translate, reducedMotion]);

  return (
    <Animated.View style={[{ opacity, transform: [{ translateY: translate }] }, style]}>
      {children}
    </Animated.View>
  );
}
