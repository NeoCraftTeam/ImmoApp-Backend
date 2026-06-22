import LottieView from 'lottie-react-native';
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing } from 'react-native';
import { Paragraph, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

interface Props {
  /** Whether the underlying session/store has finished hydrating. */
  ready: boolean;
  /**
   * Optional Lottie JSON source. Accepts the same union as
   * `LottieView.props.source` : `require(...)` id, `{ uri }` remote URL,
   * or a parsed AnimationObject.
   */
  lottieSource?: React.ComponentProps<typeof LottieView>['source'];
}

/**
 * Mobile splash overlay rendered above the routed app while the
 * SessionProvider / persisted Query cache rehydrate. Once `ready=true`
 * we fade out over ~340 ms; the splash unmounts after the fade.
 *
 * Animation strategy :
 *  1. If a Lottie JSON is provided (passed via `lottieSource`), we
 *     render it full-bleed at 60 % screen — that's the production path
 *     for the brand animation.
 *  2. Fallback (no Lottie asset shipped yet): we animate the wordmark
 *     "K e y H o m e" with a staggered scale-up via RN Animated + an
 *     infinite pulsing dot row below it. Native driver, zero JS thread
 *     overhead, smooth on entry-level Android too.
 */
export function SplashView({ ready, lottieSource }: Props) {
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
      {lottieSource ? (
        <LottieView
          source={lottieSource}
          autoPlay
          loop
          style={{ width: 220, height: 220 }}
        />
      ) : (
        <WordmarkFallback />
      )}
      <Paragraph
        fontSize={13}
        fontWeight="600"
        color="rgba(255,255,255,0.85)"
        marginTop={26}
        letterSpacing={1}
      >
        Trouvez votre prochain logement
      </Paragraph>
    </Animated.View>
  );
}

/**
 * Staggered scale-up wordmark + pulsing dot row. Used when no Lottie
 * JSON is shipped — keeps the splash polished without an asset
 * dependency.
 */
function WordmarkFallback() {
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
      <YStack flexDirection="row" gap={2}>
        {letters.map((letter, i) => (
          <Animated.Text
            key={`${letter}-${i}`}
            style={{
              fontSize: 44,
              fontWeight: '900',
              color: 'white',
              letterSpacing: -1,
              transform: [
                { scale: animations[i] ?? new Animated.Value(1) },
              ],
              opacity: animations[i] ?? new Animated.Value(1),
            }}
          >
            {letter}
          </Animated.Text>
        ))}
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
