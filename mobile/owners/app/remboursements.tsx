import { RotateCcw } from '@tamagui/lucide-icons';
import { RefreshControl, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useRefunds } from '@/hooks/useRefunds';
import { brand } from '@/theme/tokens';
import { formatDateTime, formatFcfa } from '@/utils/format';
import type { RefundRequest, RefundStatus } from '@/types/refund';

const STATUS_COLOR: Record<RefundStatus, string> = {
  requested: brand.warning,
  reviewing: brand.secondary,
  approved: brand.primary,
  rejected: brand.danger,
  processed: brand.success,
};

const STATUS_LABEL: Record<RefundStatus, string> = {
  requested: 'Demandé',
  reviewing: 'En revue',
  approved: 'Approuvé',
  rejected: 'Refusé',
  processed: 'Traité',
};

function RefundRow({ r }: { r: RefundRequest }) {
  const color = STATUS_COLOR[r.status] ?? brand.slate500;
  return (
    <YStack
      padding={14}
      gap={6}
      borderRadius={14}
      borderWidth={1}
      borderColor="$slate300"
      backgroundColor="$background"
    >
      <XStack alignItems="center" gap={10}>
        <YStack
          width={36}
          height={36}
          borderRadius={18}
          backgroundColor={`${color}1A`}
          alignItems="center"
          justifyContent="center"
        >
          <RotateCcw size={16} color={color} />
        </YStack>
        <YStack flex={1}>
          <Paragraph fontSize={14} fontWeight="700">
            {formatFcfa(r.amount)}
          </Paragraph>
          <Paragraph fontSize={11.5} color="$slate500">
            {formatDateTime(r.created_at)}
          </Paragraph>
        </YStack>
        <Paragraph fontSize={11} fontWeight="700" color={color}>
          {r.status_label ?? STATUS_LABEL[r.status]}
        </Paragraph>
      </XStack>
      {r.reason ? (
        <Paragraph fontSize={12.5} color="$slate700" marginLeft={46}>
          {r.reason}
        </Paragraph>
      ) : null}
    </YStack>
  );
}

export default function RefundsScreen() {
  const { isAuthenticated } = useSession();
  const { data, isLoading, isRefetching, refetch } = useRefunds(isAuthenticated);
  const list = data ?? [];

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Remboursements" />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 10 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : list.length === 0 ? (
          <YStack height={320}>
            <EmptyState
              icon={<RotateCcw size={28} color={brand.primary} />}
              title="Aucun remboursement"
              hint="Vos demandes de remboursement (caution, frais litigieux) apparaîtront ici."
            />
          </YStack>
        ) : (
          list.map((r) => <RefundRow key={r.id} r={r} />)
        )}
      </ScrollView>
    </YStack>
  );
}
