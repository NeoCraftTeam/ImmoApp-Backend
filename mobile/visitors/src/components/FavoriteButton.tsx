import { Heart } from '@tamagui/lucide-icons';
import * as Haptics from 'expo-haptics';
import { useRouter } from 'expo-router';
import { useCallback } from 'react';
import { Pressable } from 'react-native';
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
 * Re-usable heart button. Visitors who tap without an account are
 * sent to the login screen — the backend's `POST /ads/{ad}/favorite`
 * is `auth:sanctum`-gated, so we short-circuit on the client to avoid
 * an unhappy round-trip.
 *
 * The `chip` variant gives the button a 36×36 circular surface for
 * the ad-detail toolbar; `bare` overlays cleanly on top of a card's
 * image.
 *
 * Haptic feedback fires on every tap because favoriting is the only
 * primary action exposed on cards — a small physical tick reinforces
 * "yes, that registered" without waiting for the network round trip.
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

  const handlePress = useCallback(() => {
    if (!isAuthenticated) {
      router.push('/(auth)/login');
      return;
    }
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    toggle.mutate({ adId });
  }, [adId, isAuthenticated, router, toggle]);

  const iconSize = size === 'small' ? 18 : 22;
  const chipSize = size === 'small' ? 32 : 36;

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
        backgroundColor={variant === 'chip' ? 'rgba(255,255,255,0.94)' : 'transparent'}
        alignItems="center"
        justifyContent="center"
      >
        <Heart
          size={iconSize}
          color={isFavorited ? '$brand' : '$slate700'}
          fill={isFavorited ? '$brand' : 'transparent'}
        />
      </YStack>
    </Pressable>
  );
}
