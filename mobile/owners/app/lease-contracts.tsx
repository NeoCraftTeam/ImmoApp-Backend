import { CalendarRange, FileText, User } from '@tamagui/lucide-icons';
import { FlatList } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useLeases } from '@/hooks/useLeases';
import { t } from '@/i18n';
import { brand } from '@/theme/tokens';
import { formatDate, formatFcfa } from '@/utils/format';
import type { LeaseContract, LeaseStatus } from '@/types/owner';

const STATUS_COLOR: Record<LeaseStatus, string> = {
  draft: brand.slate500,
  active: brand.success,
  expired: brand.accentDark,
  terminated: brand.danger,
  archived: brand.slate500,
};

export default function LeaseContractsScreen() {
  const { isAuthenticated } = useSession();
  const { data: leases, isLoading } = useLeases(isAuthenticated);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('leases.title')} />

      {isLoading ? (
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      ) : (
        <FlatList
          data={leases ?? []}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 16, paddingBottom: 32, gap: 12 }}
          renderItem={({ item }) => <LeaseCard lease={item} />}
          ListEmptyComponent={
            <YStack height={420}>
              <EmptyState
                icon={<FileText size={28} color={brand.primary} />}
                title={t('leases.empty')}
                hint="Vos contrats de bail signés avec vos locataires apparaîtront ici."
              />
            </YStack>
          }
        />
      )}
    </YStack>
  );
}

function LeaseCard({ lease }: { lease: LeaseContract }) {
  const color = STATUS_COLOR[lease.status] ?? brand.slate500;
  const tenantName = `${lease.tenant?.firstname ?? ''} ${lease.tenant?.lastname ?? ''}`.trim();

  return (
    <YStack borderWidth={1} borderColor="$slate300" borderRadius={16} padding={14} gap={10} backgroundColor="$background">
      <XStack alignItems="center" justifyContent="space-between" gap={8}>
        <Paragraph fontSize={17} fontWeight="900" color="$slate900" flex={1} numberOfLines={1}>
          {formatFcfa(lease.monthly_rent)}
          <Paragraph fontSize={13} fontWeight="600" color="$slate500">
            {' '}
            {t('ads.perMonth')}
          </Paragraph>
        </Paragraph>
        <XStack backgroundColor={`${color}1A`} paddingHorizontal={10} paddingVertical={4} borderRadius={999}>
          <Paragraph fontSize={11} fontWeight="800" color={color}>
            {t(`leases.status.${lease.status}`)}
          </Paragraph>
        </XStack>
      </XStack>

      <XStack alignItems="center" gap={8}>
        <CalendarRange size={14} color={brand.slate500} />
        <Paragraph fontSize={13} color="$slate700">
          {formatDate(lease.lease_start)} – {formatDate(lease.lease_end)}
        </Paragraph>
      </XStack>

      {tenantName ? (
        <XStack alignItems="center" gap={8}>
          <User size={14} color={brand.slate500} />
          <Paragraph fontSize={13} color="$slate700" numberOfLines={1}>
            {tenantName}
          </Paragraph>
        </XStack>
      ) : null}
    </YStack>
  );
}
