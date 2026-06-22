import { useEffect, useRef, useState } from 'react';
import { Animated, Easing } from 'react-native';
import { Paragraph, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

interface Props {
  /** Whether the underlying session / persisted cache has hydrated. */
  ready: boolean;
}

/**
 * Owner splash overlay rendered above the routed app while the
 * SessionProvider + persisted Query cache rehydrate. Once `ready=true`
 * we fade out over ~340 ms; the splash unmounts after the fade.
 *
 * No Lottie dependency — a staggered scale-up wordmark + pulsing dot
 * (native driver, zero JS-thread overhead) keeps the splash polished
 * without shipping an animation asset.
 */
export function SplashView({ ready }: Props) {
  const fade = useRef(new Animated.Value(1)).current;
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    if (!ready) return;
    Animated.timing(fade, {
      toValue: 0,
      duration: 340,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start(() => setVisible(false));
  }, [ready, fade]);

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
      <Wordmark />
      <Paragraph
        fontSize={13}
        fontWeight="600"
        color="rgba(255,255,255,0.9)"
        marginTop={24}
        letterSpacing={1}
      >
        Votre activité immobilière, en main
      </Paragraph>
    </Animated.View>
  );
}

function Wordmark() {
  const letters = ['K', 'e', 'y', 'H', 'o', 'm', 'e'];
  const animations = useRef(letters.map(() => new Animated.Value(0.4))).current;
  const dotScale = useRef(new Animated.Value(0.6)).current;

  useEffect(() => {
    const stagger = animations.map((anim, i) =>
      Animated.timing(anim, {
        toValue: 1,
        duration: 360,
        delay: i * 60,
        easing: Easing.out(Easing.back(1.6)),
        useNativeDriver: true,
      }),
    );
    Animated.stagger(50, stagger).start();

    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(dotScale, {
          toValue: 1.6,
          duration: 700,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.timing(dotScale, {
          toValue: 0.6,
          duration: 700,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [animations, dotScale]);

  return (
    <YStack alignItems="center" gap={22}>
      <YStack flexDirection="row" gap={2} alignItems="center">
        {letters.map((letter, i) => (
          <Animated.Text
            key={`${letter}-${i}`}
            style={{
              fontSize: 42,
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
        <Animated.Text
          style={{
            fontSize: 20,
            fontWeight: '800',
            color: brand.accentLight,
            marginLeft: 6,
            transform: [{ scale: animations[6] ?? new Animated.Value(1) }],
          }}
        >
          Pro
        </Animated.Text>
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
