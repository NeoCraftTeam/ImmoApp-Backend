import { useEffect, useRef, useState } from 'react';
import { Animated, Easing } from 'react-native';
import { Paragraph, YStack } from 'tamagui';

import { useMotionPresets } from '@/hooks/useMotionPresets';
import { brand } from '@/theme/tokens';

interface Props {
  /** Whether the underlying session / persisted cache has hydrated. */
  ready: boolean;
}

/**
 * Owner splash — fond teal (`brand.primary`), pas de mention "Pro" :
 * c'est **KeyHome Owner**. La séquence dure ~1.4 s avant fade :
 *   1. Stagger lettres "K-e-y-H-o-m-e"             ~720 ms
 *   2. Halo blanc grossit autour des lettres       ~360 ms
 *   3. Tagline glisse depuis le bas                ~320 ms
 *   4. Pulse infinie du dot (parallèle aux étapes 2–3)
 * Une fois `ready=true`, on fade out en 380 ms — la totale dépasse
 * légèrement le splash visiteur comme demandé.
 */
export function SplashView({ ready }: Props) {
  const { reducedMotion, splashFadeMs } = useMotionPresets();
  const fade = useRef(new Animated.Value(1)).current;
  const [visible, setVisible] = useState(true);

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
        backgroundColor: brand.primary,
        alignItems: 'center',
        justifyContent: 'center',
        opacity: fade,
      }}
    >
      <Wordmark reducedMotion={reducedMotion} />
      <Tagline reducedMotion={reducedMotion} />
    </Animated.View>
  );
}

function Wordmark({ reducedMotion }: { reducedMotion: boolean }) {
  const letters = ['K', 'e', 'y', 'H', 'o', 'm', 'e'];
  const animations = useRef(letters.map(() => new Animated.Value(0.4))).current;
  const dotScale = useRef(new Animated.Value(0.6)).current;
  const haloScale = useRef(new Animated.Value(0)).current;
  const haloOpacity = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    // Reduced motion : wordmark statique, pas de stagger / halo / pulse.
    if (reducedMotion) {
      animations.forEach((anim) => anim.setValue(1));
      dotScale.setValue(1);
      return;
    }

    // Out-cubic sans overshoot : les lettres n'ont aucun momentum, un
    // rebond (Easing.back) serait décoratif et contraire aux règles motion.
    const stagger = animations.map((anim, i) =>
      Animated.timing(anim, {
        toValue: 1,
        duration: 360,
        delay: i * 70,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
    );
    Animated.stagger(60, stagger).start();

    // Halo qui s'étend après les lettres
    Animated.sequence([
      Animated.delay(620),
      Animated.parallel([
        Animated.timing(haloScale, {
          toValue: 1,
          duration: 460,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.sequence([
          Animated.timing(haloOpacity, {
            toValue: 0.18,
            duration: 220,
            useNativeDriver: true,
          }),
          Animated.timing(haloOpacity, {
            toValue: 0,
            duration: 240,
            useNativeDriver: true,
          }),
        ]),
      ]),
    ]).start();

    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(dotScale, {
          toValue: 1.6,
          duration: 800,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.timing(dotScale, {
          toValue: 0.6,
          duration: 800,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [animations, dotScale, haloOpacity, haloScale, reducedMotion]);

  return (
    <YStack alignItems="center" gap={24}>
      <YStack alignItems="center" justifyContent="center">
        {/* Halo blanc qui pulse autour du wordmark */}
        <Animated.View
          style={{
            position: 'absolute',
            width: 260,
            height: 90,
            borderRadius: 60,
            backgroundColor: 'white',
            opacity: haloOpacity,
            transform: [{ scale: haloScale }],
          }}
        />
        <YStack flexDirection="row" gap={2} alignItems="center">
          {letters.map((letter, i) => (
            <Animated.Text
              key={`${letter}-${i}`}
              style={{
                fontSize: 44,
                fontWeight: '900',
                color: 'white',
                letterSpacing: -1,
                transform: [{ scale: animations[i] ?? new Animated.Value(1) }],
                opacity: animations[i] ?? new Animated.Value(1),
              }}
            >
              {letter}
            </Animated.Text>
          ))}
        </YStack>
      </YStack>
      <Animated.View
        style={{
          width: 12,
          height: 12,
          borderRadius: 6,
          backgroundColor: 'white',
          transform: [{ scale: dotScale }],
        }}
      />
    </YStack>
  );
}

function Tagline({ reducedMotion }: { reducedMotion: boolean }) {
  const slide = useRef(new Animated.Value(14)).current;
  const opacity = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    // Reduced motion : fade court sans slide ni délai chorégraphié.
    if (reducedMotion) {
      slide.setValue(0);
      Animated.timing(opacity, {
        toValue: 1,
        duration: 200,
        useNativeDriver: true,
      }).start();
      return;
    }

    Animated.sequence([
      Animated.delay(820),
      Animated.parallel([
        Animated.timing(slide, {
          toValue: 0,
          duration: 360,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(opacity, {
          toValue: 1,
          duration: 360,
          useNativeDriver: true,
        }),
      ]),
    ]).start();
  }, [slide, opacity, reducedMotion]);

  return (
    <Animated.View
      style={{
        marginTop: 26,
        opacity,
        transform: [{ translateY: slide }],
      }}
    >
      <Paragraph
        fontSize={13}
        fontWeight="700"
        color="rgba(255,255,255,0.9)"
        letterSpacing={1.4}
        textAlign="center"
      >
        Votre activité immobilière, en main
      </Paragraph>
    </Animated.View>
  );
}
