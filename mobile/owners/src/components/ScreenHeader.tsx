import { ArrowLeft } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import type { ReactNode } from 'react';
import { Pressable } from 'react-native';
import { H1, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

/**
 * Standard screen header with optional back button, title, subtitle and
 * a trailing slot for actions. Handles the top safe-area inset so every
 * pushed screen lines up consistently.
 */
export function ScreenHeader({
  title,
  subtitle,
  back = true,
  right,
}: {
  title: string;
  subtitle?: string;
  back?: boolean;
  right?: ReactNode;
}) {
  const router = useRouter();
  const insets = useSafeAreaInsets();

  return (
    <YStack
      paddingTop={insets.top + 10}
      paddingHorizontal={16}
      paddingBottom={12}
      backgroundColor="$background"
      borderBottomWidth={0.5}
      borderBottomColor="$slate300"
      gap={subtitle ? 4 : 0}
    >
      <XStack alignItems="center" gap={10}>
        {back ? (
          <Pressable onPress={() => router.back()} hitSlop={10} accessibilityLabel="Retour">
            <YStack
              width={36}
              height={36}
              borderRadius={18}
              backgroundColor="$slate100"
              alignItems="center"
              justifyContent="center"
            >
              <ArrowLeft size={18} color={brand.slate700} />
            </YStack>
          </Pressable>
        ) : null}
        <H1 fontSize={22} fontWeight="800" flex={1} numberOfLines={1}>
          {title}
        </H1>
        {right}
      </XStack>
      {subtitle ? (
        <Paragraph fontSize={13} color="$slate500" marginLeft={back ? 46 : 0}>
          {subtitle}
        </Paragraph>
      ) : null}
    </YStack>
  );
}
