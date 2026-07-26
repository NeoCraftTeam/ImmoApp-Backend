import { useEffect, useRef, type ReactNode } from 'react';
import { Animated, Easing, type StyleProp, type ViewStyle } from 'react-native';

import { useReducedMotion } from '@/hooks/useReducedMotion';

interface Props {
  children: ReactNode;
  /** ms before the animation starts (stagger via array index). */
  delay?: number;
  /** Final transform direction. */
  from?: 'bottom' | 'top' | 'none';
  /** Override the default 320 ms duration. */
  duration?: number;
  style?: StyleProp<ViewStyle>;
}

/**
 * Mounts an animated wrapper that fades + slides its children into
 * view on first paint. Native-driver only (transform + opacity) so
 * scrolling stays buttery on entry-level Android.
 *
 * Stagger pattern : pass `delay={index * 60}` when mapping a list to
 * get a "cascade" entrance (Airbnb / Notion feel) without a heavy
 * animation library.
 */
export function FadeIn({
  children,
  delay = 0,
  from = 'bottom',
  duration = 320,
  style,
}: Props) {
  const reducedMotion = useReducedMotion();
  const opacity = useRef(new Animated.Value(0)).current;
  const translate = useRef(new Animated.Value(from === 'none' ? 0 : 14)).current;

  useEffect(() => {
    // Reduced motion : cross-fade court sans translation (pas de slide),
    // conformément au HIG — l'opacité aide la compréhension, le
    // déplacement est vestibulaire.
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
        duration,
        delay,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(translate, {
        toValue: 0,
        duration,
        delay,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start();
  }, [opacity, translate, delay, duration, reducedMotion]);

  const transform =
    from === 'top'
      ? [{ translateY: Animated.multiply(translate, -1) }]
      : from === 'bottom'
        ? [{ translateY: translate }]
        : [];

  return (
    <Animated.View style={[{ opacity, transform }, style]}>
      {children}
    </Animated.View>
  );
}
