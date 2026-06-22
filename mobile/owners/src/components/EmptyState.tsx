import type { ReactNode } from 'react';
import { Button, Paragraph, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

/**
 * Centred empty-state block — icon, title, hint, and an optional CTA.
 * Reused across every list screen (ads, viewings, tenants, reviews…).
 */
export function EmptyState({
  icon,
  title,
  hint,
  ctaLabel,
  onPressCta,
}: {
  icon?: ReactNode;
  title: string;
  hint?: string;
  ctaLabel?: string;
  onPressCta?: () => void;
}) {
  return (
    <YStack flex={1} alignItems="center" justifyContent="center" padding={32} gap={12}>
      {icon ? (
        <YStack
          width={72}
          height={72}
          borderRadius={36}
          alignItems="center"
          justifyContent="center"
          backgroundColor={brand.primaryAlpha10}
        >
          {icon}
        </YStack>
      ) : null}
      <Paragraph fontSize={17} fontWeight="800" color="$slate900" textAlign="center">
        {title}
      </Paragraph>
      {hint ? (
        <Paragraph fontSize={13.5} color="$slate500" textAlign="center" lineHeight={20}>
          {hint}
        </Paragraph>
      ) : null}
      {ctaLabel && onPressCta ? (
        <Button
          marginTop={6}
          size="$4"
          backgroundColor="$brand"
          color="white"
          fontWeight="700"
          borderRadius={12}
          onPress={onPressCta}
        >
          {ctaLabel}
        </Button>
      ) : null}
    </YStack>
  );
}
