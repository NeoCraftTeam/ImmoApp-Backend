import { useEffect, useRef } from 'react';
import { Animated, Easing, type DimensionValue } from 'react-native';
import { useTheme } from 'tamagui';

import { useReducedMotion } from '@/hooks/useReducedMotion';

interface Props {
  width?: DimensionValue;
  height?: DimensionValue;
  /** Rayon de bordure — défaut 8. */
  radius?: number;
  /** Override de la couleur de fond (sinon token thème `$slate100`). */
  backgroundColor?: string;
  /** Marge custom inline. */
  style?: object;
}

/**
 * Primitif Skeleton — rectangle qui pulse en boucle infinie via
 * `useNativeDriver: true`. À composer librement pour faire un skeleton
 * de n'importe quelle géométrie (carte, ligne de texte, avatar…).
 *
 *   <Skeleton width="80%" height={14} />              ← ligne texte
 *   <Skeleton width="100%" height={200} radius={14} /> ← image hero
 *   <Skeleton width={48} height={48} radius={24} />    ← avatar
 *
 * Reduced motion : placeholder statique, pas de boucle d'opacité.
 */
export function Skeleton({
  width = '100%',
  height = 12,
  radius = 8,
  backgroundColor,
  style,
}: Props) {
  const theme = useTheme();
  const reducedMotion = useReducedMotion();
  const resolvedBackground = backgroundColor ?? theme.slate100?.val ?? '#F1F5F9';
  const pulse = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (reducedMotion) {
      pulse.setValue(0.5);
      return;
    }

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
  }, [pulse, reducedMotion]);

  const opacity = pulse.interpolate({
    inputRange: [0, 1],
    outputRange: [0.55, 1],
  });

  return (
    <Animated.View
      style={[
        {
          width,
          height,
          borderRadius: radius,
          backgroundColor: resolvedBackground,
          opacity,
        },
        style,
      ]}
    />
  );
}
