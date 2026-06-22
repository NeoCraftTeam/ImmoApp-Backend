import { Heart } from '@tamagui/lucide-icons';
import * as Haptics from 'expo-haptics';
import { useRouter } from 'expo-router';
import { useCallback, useRef } from 'react';
import { Animated, Easing, Pressable } from 'react-native';
import { YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { useToggleFavorite } from '@/hooks/useToggleFavorite';

interface Props {
  adId: string;
  isFavorited: boolean;
  size?: 'small' | 'medium';
  /** Render variant — `bare` = no chrome, `chip` = circular filled bg. */
  variant?: 'bare' | 'chip';
}

/**
 * Heart bookmark with a keyframed "burst" on add — the icon springs
 * through [1, 1.5, 0.85, 1.15, 1] mirroring the web component's
 * framer-motion sequence. Animation runs on the UI thread via RN's
 * `Animated` with `useNativeDriver: true`.
 *
 * Visitors who tap without an account are sent to the login screen
 * (the backend's POST /ads/{ad}/favorite is sanctum-gated).
 */
export function FavoriteButton({
  adId,
  isFavorited,
  size = 'medium',
  variant = 'chip',
}: Props) {
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const toggle = useToggleFavorite();

  const scale = useRef(new Animated.Value(1)).current;

  const playBurst = useCallback(() => {
    const ease = Easing.inOut(Easing.ease);
    scale.setValue(1);
    Animated.sequence([
      Animated.timing(scale, { toValue: 1.5, duration: 130, easing: ease, useNativeDriver: true }),
      Animated.timing(scale, { toValue: 0.85, duration: 110, easing: ease, useNativeDriver: true }),
      Animated.timing(scale, { toValue: 1.15, duration: 130, easing: ease, useNativeDriver: true }),
      Animated.timing(scale, { toValue: 1, duration: 130, easing: ease, useNativeDriver: true }),
    ]).start();
  }, [scale]);

  const handlePress = useCallback(() => {
    if (!isAuthenticated) {
      router.push('/(auth)/login');
      return;
    }
    if (!isFavorited) {
      playBurst();
    }
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    toggle.mutate({ adId });
  }, [adId, isAuthenticated, isFavorited, playBurst, router, toggle]);

  const iconSize = size === 'small' ? 16 : 20;
  const chipSize = size === 'small' ? 30 : 38;

  return (
    <Pressable
      onPress={handlePress}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityState={{ selected: isFavorited }}
      accessibilityLabel={
        isFavorited ? 'Retirer des favoris' : 'Ajouter aux favoris'
      }
    >
      <YStack
        width={variant === 'chip' ? chipSize : undefined}
        height={variant === 'chip' ? chipSize : undefined}
        borderRadius={variant === 'chip' ? chipSize / 2 : undefined}
        backgroundColor={
          variant === 'chip' ? 'rgba(255,255,255,0.92)' : 'transparent'
        }
        alignItems="center"
        justifyContent="center"
      >
        <Animated.View style={{ transform: [{ scale }] }}>
          <Heart
            size={iconSize}
            color={isFavorited ? '$brand' : '$slate700'}
            fill={isFavorited ? '$brand' : 'transparent'}
          />
        </Animated.View>
      </YStack>
    </Pressable>
  );
}
