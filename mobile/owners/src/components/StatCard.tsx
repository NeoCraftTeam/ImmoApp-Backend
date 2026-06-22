import type { ReactNode } from 'react';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

/**
 * Compact KPI card used on the dashboard. Shows a value, a label, and an
 * optional leading icon tinted with a per-card accent colour.
 */
export function StatCard({
  label,
  value,
  icon,
  accent = brand.primary,
  hint,
}: {
  label: string;
  value: string | number;
  icon?: ReactNode;
  accent?: string;
  hint?: string;
}) {
  return (
    <YStack
      flex={1}
      minWidth={150}
      backgroundColor="$background"
      borderWidth={1}
      borderColor="$slate300"
      borderRadius={16}
      padding={14}
      gap={10}
    >
      <XStack alignItems="center" justifyContent="space-between">
        <Paragraph fontSize={12} fontWeight="600" color="$slate500" flex={1} numberOfLines={1}>
          {label}
        </Paragraph>
        {icon ? (
          <YStack
            width={30}
            height={30}
            borderRadius={9}
            alignItems="center"
            justifyContent="center"
            backgroundColor={`${accent}1A`}
          >
            {icon}
          </YStack>
        ) : null}
      </XStack>
      <Paragraph fontSize={24} fontWeight="900" color="$slate900" letterSpacing={-0.5}>
        {value}
      </Paragraph>
      {hint ? (
        <Paragraph fontSize={11} color="$slate500">
          {hint}
        </Paragraph>
      ) : null}
    </YStack>
  );
}
