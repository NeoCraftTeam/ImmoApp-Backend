import { useEffect, useRef, useState } from 'react';
import { Animated, Easing } from 'react-native';
import { Paragraph, YStack } from 'tamagui';

import { KeyHomeLogo } from '@/components/KeyHomeLogo';
import { useMotionPresets } from '@/hooks/useMotionPresets';

/** Fond clair façon splash éditorial (proche du blanc cassé Anthropic). */
const SPLASH_BG = '#F7F5F2';

interface Props {
  /** Whether the underlying session/store has finished hydrating. */
  ready: boolean;
}

/**
 * Splash overlay rendu au-dessus de l'app pendant la réhydratation de la
 * session / du cache Query.
 *
 * Parti-pris visuel : sobre et éditorial, à la manière du splash Claude —
 * logo centré + signature « BY NEOCRAFT » en bas (uppercase, tracking
 * large, gris). Animation minimale (fade + léger scale-up), pas de
 * stagger lettre-par-lettre ni de halo pulsant (anti « AI-slop »).
 *
 * `SplashGate` (dans `_layout.tsx`) impose un min-delay ; une fois
 * `ready=true`, l'overlay fade out en 380 ms.
 */
export function SplashView({ ready }: Props) {
  const { reducedMotion, splashFadeMs } = useMotionPresets();
  const fade = useRef(new Animated.Value(1)).current;
  const logoOpacity = useRef(new Animated.Value(0)).current;
  const logoScale = useRef(new Animated.Value(0.96)).current;
  const signatureOpacity = useRef(new Animated.Value(0)).current;
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    // Reduced motion : cross-fades seuls, pas de scale-up.
    if (reducedMotion) {
      logoScale.setValue(1);
      Animated.parallel([
        Animated.timing(logoOpacity, {
          toValue: 1,
          duration: 200,
          useNativeDriver: true,
        }),
        Animated.timing(signatureOpacity, {
          toValue: 1,
          duration: 200,
          useNativeDriver: true,
        }),
      ]).start();
      return;
    }

    Animated.parallel([
      Animated.timing(logoOpacity, {
        toValue: 1,
        duration: 520,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(logoScale, {
        toValue: 1,
        duration: 620,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(signatureOpacity, {
        toValue: 1,
        duration: 460,
        delay: 260,
        useNativeDriver: true,
      }),
    ]).start();
  }, [logoOpacity, logoScale, signatureOpacity, reducedMotion]);

  useEffect(() => {
    if (!ready) return;
    Animated.timing(fade, {
      toValue: 0,
      duration: splashFadeMs,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start(() => setVisible(false));
  }, [ready, fade, splashFadeMs]);

  if (!visible) return null;

  return (
    <Animated.View
      pointerEvents="none"
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: SPLASH_BG,
        alignItems: 'center',
        justifyContent: 'center',
        opacity: fade,
      }}
    >
      <Animated.View
        style={{ opacity: logoOpacity, transform: [{ scale: logoScale }] }}
      >
        {/* Icône + wordmark côte à côte, taille contenue, graisse maximale
            (demande produit : « à côté du logo, pas très gros, mais en gras »). */}
        <KeyHomeLogo size={26} />
      </Animated.View>

      <Animated.View
        style={{
          position: 'absolute',
          bottom: 64,
          left: 0,
          right: 0,
          opacity: signatureOpacity,
        }}
      >
        <YStack alignItems="center">
          <Paragraph
            fontSize={13}
            lineHeight={18}
            fontWeight="900"
            color="#6E6963"
            letterSpacing={3.2}
            textAlign="center"
            textTransform="uppercase"
          >
            BY NEOCRAFT
          </Paragraph>
        </YStack>
      </Animated.View>
    </Animated.View>
  );
}
