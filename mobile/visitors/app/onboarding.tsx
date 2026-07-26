import { useRouter } from 'expo-router';
import * as SecureStore from 'expo-secure-store';
import { useCallback, useRef, useState } from 'react';
import { Platform, useWindowDimensions, FlatList, type ViewToken } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ONBOARDING_DONE_KEY } from '@/auth/storage-keys';
import { useMotionPresets } from '@/hooks/useMotionPresets';
import { i18n, t } from '@/i18n';

/**
 * Welcome carousel shown the first time the app launches. Three slides,
 * horizontal paging, a "Skip" button in the top-right, and a primary
 * CTA at the bottom. On completion we set the `onboarding.done` flag
 * so the `/` index gate redirects to `/home` next time.
 *
 * Why FlatList over a Reanimated PagerView: FlatList ships with React
 * Native, supports paging out of the box via `pagingEnabled`, and the
 * card content here is text-only — Reanimated would be overkill.
 */
export default function Onboarding() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const slides = (i18n.t('onboarding.slides') as unknown as Array<{ title: string; body: string }>) ?? [];
  const { tamaguiQuick, scrollAnimated } = useMotionPresets();
  const [activeIndex, setActiveIndex] = useState(0);
  const listRef = useRef<FlatList>(null);

  const persistDone = useCallback(async () => {
    try {
      if (Platform.OS === 'web') {
        if (typeof window !== 'undefined' && window.localStorage) {
          window.localStorage.setItem(ONBOARDING_DONE_KEY, '1');
        }
      } else {
        await SecureStore.setItemAsync(ONBOARDING_DONE_KEY, '1');
      }
    } catch {
      /* non-fatal — user will just see onboarding again next time */
    }
  }, []);

  const handleNext = useCallback(async () => {
    if (activeIndex < slides.length - 1) {
      listRef.current?.scrollToIndex({ index: activeIndex + 1, animated: scrollAnimated });
      return;
    }
    await persistDone();
    router.replace('/(tabs)/home');
  }, [activeIndex, slides.length, persistDone, router, scrollAnimated]);

  const handleSkip = useCallback(async () => {
    await persistDone();
    router.replace('/(tabs)/home');
  }, [persistDone, router]);

  const onViewableItemsChanged = useRef(({ viewableItems }: { viewableItems: ViewToken[] }) => {
    const first = viewableItems[0];
    if (first?.index != null) setActiveIndex(first.index);
  }).current;

  return (
    <YStack flex={1} backgroundColor="$background" paddingTop={insets.top + 8}>
      <XStack justifyContent="flex-end" paddingHorizontal="$4" paddingVertical="$2">
        <Button size="$2" chromeless onPress={handleSkip}>
          {t('common.skip')}
        </Button>
      </XStack>

      <FlatList
        ref={listRef}
        data={slides}
        keyExtractor={(_item, idx) => `slide-${idx}`}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onViewableItemsChanged={onViewableItemsChanged}
        viewabilityConfig={{ itemVisiblePercentThreshold: 60 }}
        renderItem={({ item }) => (
          <YStack
            width={width}
            paddingHorizontal="$6"
            justifyContent="center"
            alignItems="center"
            gap="$4"
          >
            <H2 textAlign="center">{item.title}</H2>
            <Paragraph textAlign="center" color="$slate500" size="$5">
              {item.body}
            </Paragraph>
          </YStack>
        )}
      />

      <XStack justifyContent="center" gap="$2" paddingVertical="$3">
        {slides.map((_, idx) => (
          <YStack
            key={`dot-${idx}`}
            width={idx === activeIndex ? 24 : 8}
            height={8}
            borderRadius={4}
            backgroundColor={idx === activeIndex ? '$brand' : '$slate300'}
            animation={tamaguiQuick}
          />
        ))}
      </XStack>

      <YStack paddingHorizontal="$4" paddingBottom={insets.bottom + 16} gap="$3">
        <Button
          size="$5"
          backgroundColor="$brand"
          color="$brandText"
          fontWeight="700"
          onPress={handleNext}
          accessibilityRole="button"
          accessibilityLabel={
            activeIndex < slides.length - 1
              ? t('common.next')
              : t('onboarding.getStarted')
          }
        >
          {activeIndex < slides.length - 1
            ? t('common.next')
            : t('onboarding.getStarted')}
        </Button>
        <Button
          size="$4"
          chromeless
          onPress={() => router.push('/(auth)/login')}
          accessibilityRole="link"
        >
          {t('onboarding.iHaveAccount')}
        </Button>
      </YStack>
    </YStack>
  );
}
