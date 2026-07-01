import { useEffect, useRef } from 'react';
import { Animated, Easing, type DimensionValue } from 'react-native';

import { useAppTheme } from '@/providers/ThemeProvider';
import { brand } from '@/theme/tokens';

interface Props {
  width?: DimensionValue;
  height?: DimensionValue;
  /** Rayon de bordure — défaut 8. */
  radius?: number;
  /** Override de la couleur de fond (sinon adaptée au thème). */
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
 * Une seule animation Animated.Value partagée par composant — N
 * skeletons rendus en même temps ne créent qu'1 driver natif par
 * instance. Pour des dizaines de skeletons, prévoir un context shared
 * mais c'est rarement nécessaire en pratique.
 */
export function Skeleton({
  width = '100%',
  height = 12,
  radius = 8,
  backgroundColor,
  style,
}: Props) {
  const { scheme } = useAppTheme();
  const resolvedBackground =
    backgroundColor ?? (scheme === 'dark' ? brand.slate700 : brand.slate100);
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
