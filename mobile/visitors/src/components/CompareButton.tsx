import { GitCompareArrows } from '@tamagui/lucide-icons';
import * as Haptics from 'expo-haptics';
import { useCallback } from 'react';
import { Pressable } from 'react-native';
import { YStack } from 'tamagui';

import { COMPARE_MAX_ITEMS, useCompare } from '@/providers/CompareProvider';
import { useThemeColors } from '@/theme/useThemeColors';
import type { Ad } from '@/types/ad';

interface Props {
  ad: Ad;
  size?: 'small' | 'medium';
}

/**
 * Small toggle that adds / removes an ad from the visitor's compare
 * set. Lives next to the favorite button on cards + detail. The button
 * is non-destructive — tapping a 5th ad when the set is full does
 * nothing visible; the floating CompareBar surfaces the "max reached"
 * state instead so the user knows what's blocking.
 *
 * Haptic feedback mirrors the favorite button so the two micro-actions
 * feel like siblings.
 */
export function CompareButton({ ad, size = 'small' }: Props) {
  const colors = useThemeColors();
  const { isCompared, isFull, toggle } = useCompare();
  const active = isCompared(ad.id);

  const handlePress = useCallback(() => {
    if (!active && isFull) {
      // Selection is full — refuse silently. The CompareBar at the
      // bottom shows "4/4 — comparer" so the user has the affordance
      // to either remove one or jump to the table.
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      return;
    }
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    toggle(ad);
  }, [ad, active, isFull, toggle]);

  const chipSize = size === 'small' ? 32 : 36;
  const iconSize = size === 'small' ? 16 : 18;

  return (
    <Pressable
      onPress={handlePress}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityState={{ selected: active, disabled: !active && isFull }}
      accessibilityLabel={
        active
          ? 'Retirer de la comparaison'
          : isFull
            ? `Maximum ${COMPARE_MAX_ITEMS} annonces comparées`
            : 'Ajouter à la comparaison'
      }
    >
      <YStack
        width={chipSize}
        height={chipSize}
        borderRadius={chipSize / 2}
        backgroundColor={active ? '$brand' : colors.chromeOverlay}
        alignItems="center"
        justifyContent="center"
      >
        <GitCompareArrows
          size={iconSize}
          color={active ? '$brandText' : colors.mutedIcon}
        />
      </YStack>
    </Pressable>
  );
}
