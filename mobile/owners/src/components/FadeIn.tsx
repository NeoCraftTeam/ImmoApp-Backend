import { useEffect, useRef, type ReactNode } from 'react';
import { Animated, type ViewStyle } from 'react-native';

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
  const opacity = useRef(new Animated.Value(0)).current;
  const translate = useRef(new Animated.Value(distance)).current;

  useEffect(() => {
    Animated.parallel([
      Animated.timing(opacity, {
        toValue: 1,
        duration: 320,
        delay,
        useNativeDriver: true,
      }),
      Animated.timing(translate, {
        toValue: 0,
        duration: 320,
        delay,
        useNativeDriver: true,
      }),
    ]).start();
  }, [delay, opacity, translate]);

  return (
    <Animated.View style={[{ opacity, transform: [{ translateY: translate }] }, style]}>
      {children}
    </Animated.View>
  );
}
