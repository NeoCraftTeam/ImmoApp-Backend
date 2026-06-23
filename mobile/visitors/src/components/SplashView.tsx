import LottieView from 'lottie-react-native';
import { KeyRound } from '@tamagui/lucide-icons';
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
 * SessionProvider / persisted Query cache rehydrate.
 *
 * Timing (séquence ~1.7 s avant fade) :
 *   1. 0–480 ms    : pastille clé qui zoom-in + rotation (back easing)
 *   2. 80–520 ms   : wordmark "K e y H o m e" stagger lettre par lettre
 *   3. 620–1100 ms : halo blanc qui pulse autour de la pastille
 *   4. 900–1280 ms : tagline qui slide-up depuis le bas avec fade
 *   5. dot infini pendant tout le temps
 *
 * Le min-delay est imposé par `SplashGate` côté `_layout.tsx` (1500 ms)
 * pour laisser à l'utilisateur le temps de voir l'animation jusqu'au
 * bout, pas seulement un flash. Une fois `ready=true`, fade out 380 ms.
 */
export function SplashView({ ready, lottieSource }: Props) {
  const fade = useRef(new Animated.Value(1)).current;
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    if (!ready) return;
    Animated.timing(fade, {
      toValue: 0,
      duration: 380,
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
      <Tagline />
    </Animated.View>
  );
}

/**
 * Pastille clé + wordmark "KeyHome" animés.
 * Native driver, zéro JS-thread overhead.
 */
function WordmarkFallback() {
  const letters = ['K', 'e', 'y', 'H', 'o', 'm', 'e'];
  const letterAnims = useRef(letters.map(() => new Animated.Value(0.4))).current;
  const dotScale = useRef(new Animated.Value(0.6)).current;
  const iconScale = useRef(new Animated.Value(0.4)).current;
  const iconRotate = useRef(new Animated.Value(0)).current;
  const haloScale = useRef(new Animated.Value(0)).current;
  const haloOpacity = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    // 1. Pastille clé : zoom-in + rotation 22° pour signaler la clé qui tourne
    Animated.parallel([
      Animated.timing(iconScale, {
        toValue: 1,
        duration: 480,
        easing: Easing.out(Easing.back(1.8)),
        useNativeDriver: true,
      }),
      Animated.timing(iconRotate, {
        toValue: 1,
        duration: 520,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start();

    // 2. Wordmark : stagger lettre par lettre, démarre dès 80 ms
    const stagger = letterAnims.map((anim, i) =>
      Animated.timing(anim, {
        toValue: 1,
        duration: 380,
        delay: 80 + i * 70,
        easing: Easing.out(Easing.back(1.6)),
        useNativeDriver: true,
      }),
    );
    Animated.stagger(70, stagger).start();

    // 3. Halo blanc qui s'étend après les lettres (~620 ms)
    Animated.sequence([
      Animated.delay(620),
      Animated.parallel([
        Animated.timing(haloScale, {
          toValue: 1,
          duration: 480,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.sequence([
          Animated.timing(haloOpacity, {
            toValue: 0.22,
            duration: 240,
            useNativeDriver: true,
          }),
          Animated.timing(haloOpacity, {
            toValue: 0,
            duration: 260,
            useNativeDriver: true,
          }),
        ]),
      ]),
    ]).start();

    // Dot pulse infini (parallèle aux 3 étapes ci-dessus)
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
  }, [letterAnims, dotScale, iconScale, iconRotate, haloScale, haloOpacity]);

  const rotation = iconRotate.interpolate({
    inputRange: [0, 1],
    outputRange: ['-22deg', '0deg'],
  });

  return (
    <YStack alignItems="center" gap={22}>
      {/* Pastille clé avec halo */}
      <YStack alignItems="center" justifyContent="center" width={92} height={92}>
        <Animated.View
          style={{
            position: 'absolute',
            width: 130,
            height: 130,
            borderRadius: 65,
            backgroundColor: 'white',
            opacity: haloOpacity,
            transform: [{ scale: haloScale }],
          }}
        />
        <Animated.View
          style={{
            width: 72,
            height: 72,
            borderRadius: 36,
            backgroundColor: 'rgba(255,255,255,0.18)',
            alignItems: 'center',
            justifyContent: 'center',
            transform: [{ scale: iconScale }, { rotate: rotation }],
          }}
        >
          <KeyRound size={38} color="white" strokeWidth={2.4} />
        </Animated.View>
      </YStack>

      {/* Wordmark animé lettre par lettre */}
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
                { scale: letterAnims[i] ?? new Animated.Value(1) },
              ],
              opacity: letterAnims[i] ?? new Animated.Value(1),
            }}
          >
            {letter}
          </Animated.Text>
        ))}
      </YStack>

      {/* Dot pulsant */}
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

function Tagline() {
  const slide = useRef(new Animated.Value(14)).current;
  const opacity = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.sequence([
      Animated.delay(900),
      Animated.parallel([
        Animated.timing(slide, {
          toValue: 0,
          duration: 380,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(opacity, {
          toValue: 1,
          duration: 380,
          useNativeDriver: true,
        }),
      ]),
    ]).start();
  }, [slide, opacity]);

  return (
    <Animated.View
      style={{
        marginTop: 28,
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
        Trouvez votre prochain logement
      </Paragraph>
    </Animated.View>
  );
}
