import { BarChart3, Printer, Rocket } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useRef, useState } from 'react';
import { Dimensions, FlatList, Platform, Pressable, type ViewToken } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { Button, H1, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ONBOARDING_DONE_KEY } from '@/auth/storage-keys';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

const { width } = Dimensions.get('window');

const SLIDES = [
  { icon: BarChart3, color: brand.primary },
  { icon: Rocket, color: brand.secondary },
  { icon: Printer, color: brand.accent },
] as const;

/**
 * First-launch carousel for the owner app. Three slides pulled from the
 * i18n `onboarding.slides` array, each with a tinted feature icon. On
 * finish we persist the done flag and route to login.
 */
export default function Onboarding() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [index, setIndex] = useState(0);
  const listRef = useRef<FlatList>(null);
  const slides = t('onboarding.slides') as unknown as { title: string; body: string }[];

  const onViewRef = useRef((info: { viewableItems: ViewToken[] }) => {
    const first = info.viewableItems[0];
    if (first?.index != null) setIndex(first.index);
  });

  const finish = async () => {
    try {
      if (Platform.OS === 'web') {
        window.localStorage?.setItem(ONBOARDING_DONE_KEY, '1');
      } else {
        await SecureStore.setItemAsync(ONBOARDING_DONE_KEY, '1');
      }
    } catch {
      /* non-fatal */
    }
    router.replace('/(auth)/login');
  };

  const next = () => {
    if (index < slides.length - 1) {
      listRef.current?.scrollToIndex({ index: index + 1 });
    } else {
      finish();
    }
  };

  return (
    <YStack flex={1} backgroundColor="$background" paddingTop={insets.top} paddingBottom={insets.bottom + 16}>
      <XStack justifyContent="flex-end" paddingHorizontal={20} paddingTop={8}>
        <Pressable onPress={finish} hitSlop={10}>
          <Paragraph fontSize={14} fontWeight="600" color="$slate500">
            {t('common.skip')}
          </Paragraph>
        </Pressable>
      </XStack>

      <FlatList
        ref={listRef}
        data={slides}
        keyExtractor={(_, i) => String(i)}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onViewableItemsChanged={onViewRef.current}
        viewabilityConfig={{ itemVisiblePercentThreshold: 60 }}
        renderItem={({ item, index: i }) => {
          const Icon = SLIDES[i]?.icon ?? BarChart3;
          const color = SLIDES[i]?.color ?? brand.primary;
          return (
            <YStack width={width} alignItems="center" justifyContent="center" paddingHorizontal={32} gap={28}>
              <YStack
                width={132}
                height={132}
                borderRadius={66}
                alignItems="center"
                justifyContent="center"
                backgroundColor={`${color}1A`}
              >
                <Icon size={56} color={color} />
              </YStack>
              <YStack gap={12} alignItems="center">
                <H1 fontSize={26} fontWeight="900" textAlign="center" color="$slate900">
                  {item.title}
                </H1>
                <Paragraph fontSize={15} color="$slate500" textAlign="center" lineHeight={22}>
                  {item.body}
                </Paragraph>
              </YStack>
            </YStack>
          );
        }}
      />

      <XStack justifyContent="center" gap={8} marginVertical={24}>
        {slides.map((_, i) => (
          <YStack
            key={i}
            width={i === index ? 24 : 8}
            height={8}
            borderRadius={4}
            backgroundColor={i === index ? brand.primary : brand.slate300}
          />
        ))}
      </XStack>

      <YStack paddingHorizontal={24} gap={10}>
        <Button size="$5" backgroundColor="$brand" color="white" fontWeight="800" borderRadius={14} onPress={next}>
          {index === slides.length - 1 ? t('onboarding.getStarted') : t('common.next')}
        </Button>
        <Button size="$4" chromeless onPress={finish}>
          <Paragraph color="$slate500" fontWeight="600">
            {t('onboarding.iHaveAccount')}
          </Paragraph>
        </Button>
      </YStack>
    </YStack>
  );
}
