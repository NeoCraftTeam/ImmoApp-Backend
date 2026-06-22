import { Pressable, type StyleProp, type ViewStyle } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import type { ReactNode } from 'react';

import { brand } from '@/theme/tokens';

interface Props {
  /** Lucide icon ou autre — rendu dans le rond pastel central. */
  icon: ReactNode;
  title: string;
  body?: string;
  /** Action principale (CTA pill brand). */
  action?: { label: string; onPress: () => void };
  /** Action secondaire (lien underline sous le CTA). */
  secondary?: { label: string; onPress: () => void };
  style?: StyleProp<ViewStyle>;
}

/**
 * Réutilisable empty/error state — large icon centrée dans un rond
 * pastel, titre bold, body slate500, CTA pill brand + lien secondaire.
 * Pattern Airbnb / Notion / Apple Mail.
 */
export function EmptyState({ icon, title, body, action, secondary, style }: Props) {
  return (
    <YStack
      flex={1}
      alignItems="center"
      justifyContent="center"
      padding={24}
      gap={12}
      style={style}
    >
      <YStack
        width={72}
        height={72}
        borderRadius={36}
        backgroundColor="$slate100"
        alignItems="center"
        justifyContent="center"
      >
        {icon}
      </YStack>
      <Paragraph fontSize={16} fontWeight="800" color="$slate900" textAlign="center">
        {title}
      </Paragraph>
      {body && (
        <Paragraph
          fontSize={13}
          color="$slate500"
          textAlign="center"
          lineHeight={20}
          maxWidth={300}
        >
          {body}
        </Paragraph>
      )}
      {action && (
        <Pressable
          onPress={action.onPress}
          hitSlop={6}
          accessibilityRole="button"
          accessibilityLabel={action.label}
        >
          <XStack
            marginTop={6}
            paddingHorizontal={18}
            paddingVertical={11}
            borderRadius={999}
            backgroundColor={brand.primary}
          >
            <Paragraph color="white" fontWeight="700" fontSize={13.5}>
              {action.label}
            </Paragraph>
          </XStack>
        </Pressable>
      )}
      {secondary && (
        <Pressable
          onPress={secondary.onPress}
          hitSlop={4}
          accessibilityRole="button"
          accessibilityLabel={secondary.label}
        >
          <Paragraph
            fontSize={13}
            color={brand.slate500}
            fontWeight="600"
            textDecorationLine="underline"
            marginTop={2}
          >
            {secondary.label}
          </Paragraph>
        </Pressable>
      )}
    </YStack>
  );
}
