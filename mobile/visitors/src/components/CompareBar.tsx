import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { X } from '@tamagui/lucide-icons';
import { Pressable } from 'react-native';
import { Button, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { COMPARE_MAX_ITEMS, useCompare } from '@/providers/CompareProvider';
import { t } from '@/i18n';

const AVATAR_SIZE = 32;

/**
 * Sticky bottom bar that appears whenever the visitor has 1+ ads in
 * their compare set. Shows the avatar stack (cover images), the count
 * "N/4", a primary "Comparer" CTA, and a clear button.
 *
 * Designed to float above the tab bar — we add the tab bar's height
 * (~64 px on Android, ~83 px on iOS with home-indicator) to our bottom
 * offset so the bar doesn't sit on top of the tabs.
 *
 * Hidden entirely when the set is empty so it doesn't add visual
 * clutter to the default browsing state.
 */
export function CompareBar() {
  const { items, clear } = useCompare();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  if (items.length === 0) return null;

  return (
    <YStack
      position="absolute"
      left={12}
      right={12}
      // 56 px clears the bottom-tab bar on iOS (49 + safe-area) and
      // Android (64 px tab on most devices); both engines collapse to
      // the value just inside the bar even on tall-letterbox phones.
      bottom={insets.bottom + 56}
      borderRadius={16}
      backgroundColor="$slate900"
      paddingHorizontal="$3"
      paddingVertical="$2"
      shadowColor="#000"
      shadowOpacity={0.25}
      shadowRadius={12}
      shadowOffset={{ width: 0, height: 4 }}
      elevation={6}
    >
      <XStack alignItems="center" gap="$3">
        {/* Avatar stack */}
        <XStack flex={1} alignItems="center">
          {items.map((ad, idx) => {
            const cover = ad.images.find((i) => i.is_primary) ?? ad.images[0];
            return (
              <YStack
                key={ad.id}
                width={AVATAR_SIZE}
                height={AVATAR_SIZE}
                borderRadius={AVATAR_SIZE / 2}
                marginLeft={idx === 0 ? 0 : -10}
                borderWidth={2}
                borderColor="$slate900"
                overflow="hidden"
                backgroundColor="$slate700"
              >
                {cover?.thumb || cover?.url ? (
                  <Image
                    source={{ uri: cover.thumb ?? cover.url }}
                    style={{ width: '100%', height: '100%' }}
                    contentFit="cover"
                  />
                ) : null}
              </YStack>
            );
          })}
          <Paragraph color="white" size="$2" marginLeft="$2" fontWeight="600">
            {items.length}/{COMPARE_MAX_ITEMS}
          </Paragraph>
        </XStack>

        <Button
          size="$3"
          backgroundColor="$brand"
          color="$brandText"
          fontWeight="700"
          onPress={() => router.push('/compare')}
          disabled={items.length < 2}
          accessibilityLabel={t('compare.openButton')}
        >
          {t('compare.openButton')}
        </Button>

        <Pressable
          onPress={clear}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel={t('compare.clear')}
        >
          <X size={18} color="white" />
        </Pressable>
      </XStack>
    </YStack>
  );
}
