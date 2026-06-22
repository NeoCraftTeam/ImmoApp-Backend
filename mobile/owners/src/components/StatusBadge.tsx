import { Paragraph, XStack } from 'tamagui';

import { AD_STATUS_META } from '@/theme/tokens';

/**
 * Coloured status pill for an ad. Reads the `AD_STATUS_META` map so the
 * label + colours stay in sync with the backend `AdStatus` enum.
 */
export function StatusBadge({ status, size = 'md' }: { status: string; size?: 'sm' | 'md' }) {
  const meta = AD_STATUS_META[status] ?? {
    label: status,
    color: '#5A5A5A',
    bg: '#F3F4F6',
  };
  const isSm = size === 'sm';
  return (
    <XStack
      backgroundColor={meta.bg}
      paddingHorizontal={isSm ? 8 : 10}
      paddingVertical={isSm ? 3 : 4}
      borderRadius={999}
      alignSelf="flex-start"
    >
      <Paragraph fontSize={isSm ? 10 : 11.5} fontWeight="800" color={meta.color}>
        {meta.label}
      </Paragraph>
    </XStack>
  );
}
