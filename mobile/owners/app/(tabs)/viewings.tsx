import { CalendarCheck, Check, Phone, UserX } from '@tamagui/lucide-icons';
import { useMemo, useState } from 'react';
import { Alert, FlatList, Linking, Pressable, RefreshControl } from 'react-native';
import { Button, H1, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import {
  useConfirmViewing,
  useNoShowViewing,
  useViewings,
} from '@/hooks/useViewings';
import { brand } from '@/theme/tokens';
import { formatDateTime } from '@/utils/format';
import { t } from '@/i18n';
import type { ViewingReservation, ViewingStatus } from '@/types/owner';

const STATUS_FILTERS: { value: string; key: string }[] = [
  { value: '', key: 'filterAll' },
  { value: 'pending', key: 'status.pending' },
  { value: 'confirmed', key: 'status.confirmed' },
  { value: 'completed', key: 'status.completed' },
];

const STATUS_COLOR: Record<ViewingStatus, string> = {
  pending: brand.accentDark,
  confirmed: brand.success,
  cancelled: brand.danger,
  expired: brand.slate500,
  completed: brand.secondary,
  no_show: brand.danger,
};

export default function ViewingsScreen() {
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const [status, setStatus] = useState('');
  const { data: viewings, isLoading, isRefetching, refetch } = useViewings(status, isAuthenticated);

  const confirm = useConfirmViewing();
  const noShow = useNoShowViewing();

  const list = useMemo(() => viewings ?? [], [viewings]);

  const handleConfirm = (id: string) => {
    confirm.mutate(id, {
      onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
    });
  };
  const handleNoShow = (id: string) => {
    noShow.mutate(id, {
      onError: (err) => Alert.alert(t('common.error'), extractApiErrorMessage(err)),
    });
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <YStack paddingTop={insets.top + 12} paddingHorizontal={16} paddingBottom={12} gap={12}>
        <H1 fontSize={26} fontWeight="900">
          {t('viewings.title')}
        </H1>
        <FlatList
          data={STATUS_FILTERS}
          horizontal
          showsHorizontalScrollIndicator={false}
          keyExtractor={(s) => s.value || 'all'}
          contentContainerStyle={{ gap: 8 }}
          renderItem={({ item }) => {
            const active = status === item.value;
            return (
              <Pressable onPress={() => setStatus(item.value)}>
                <XStack paddingHorizontal={14} paddingVertical={8} borderRadius={999} backgroundColor={active ? brand.primary : brand.slate100}>
                  <Paragraph fontSize={13} fontWeight="700" color={active ? 'white' : brand.slate700}>
                    {t(`viewings.${item.key}`)}
                  </Paragraph>
                </XStack>
              </Pressable>
            );
          }}
        />
      </YStack>

      {isLoading ? (
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      ) : (
        <FlatList
          data={list}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 32, gap: 12 }}
          refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />}
          renderItem={({ item }) => (
            <ViewingCard
              viewing={item}
              busy={confirm.isPending || noShow.isPending}
              onConfirm={() => handleConfirm(item.id)}
              onNoShow={() => handleNoShow(item.id)}
            />
          )}
          ListEmptyComponent={
            <YStack height={420}>
              <EmptyState
                icon={<CalendarCheck size={28} color={brand.primary} />}
                title={t('viewings.empty')}
                hint={t('viewings.emptyHint')}
              />
            </YStack>
          }
        />
      )}
    </YStack>
  );
}

function ViewingCard({
  viewing,
  busy,
  onConfirm,
  onNoShow,
}: {
  viewing: ViewingReservation;
  busy: boolean;
  onConfirm: () => void;
  onNoShow: () => void;
}) {
  const fullName = `${viewing.user?.firstname ?? ''} ${viewing.user?.lastname ?? ''}`.trim() || 'Prospect';
  const color = STATUS_COLOR[viewing.status] ?? brand.slate500;
  const phone = viewing.user?.phone_number;
  const isPending = viewing.status === 'pending';
  const isConfirmed = viewing.status === 'confirmed';

  return (
    <YStack borderWidth={1} borderColor="$slate300" borderRadius={16} padding={14} gap={10} backgroundColor="$background">
      <XStack alignItems="center" justifyContent="space-between">
        <Paragraph fontSize={15} fontWeight="800" color="$slate900" flex={1} numberOfLines={1}>
          {fullName}
        </Paragraph>
        <XStack backgroundColor={`${color}1A`} paddingHorizontal={10} paddingVertical={4} borderRadius={999}>
          <Paragraph fontSize={11} fontWeight="800" color={color}>
            {t(`viewings.status.${viewing.status}`)}
          </Paragraph>
        </XStack>
      </XStack>

      <Paragraph fontSize={13} color="$slate700" numberOfLines={1}>
        {viewing.ad?.title ?? 'Annonce'}
      </Paragraph>
      <Paragraph fontSize={12.5} color="$slate500">
        {t('viewings.scheduledFor')} {formatDateTime(viewing.scheduled_at)}
      </Paragraph>
      {viewing.notes ? (
        <Paragraph fontSize={12.5} color="$slate500" fontStyle="italic">
          « {viewing.notes} »
        </Paragraph>
      ) : null}

      <XStack gap={8} marginTop={2} flexWrap="wrap">
        {phone ? (
          <Button
            size="$3"
            chromeless
            borderWidth={1}
            borderColor="$slate300"
            borderRadius={10}
            icon={<Phone size={15} color={brand.slate700} />}
            onPress={() => Linking.openURL(`tel:${phone}`)}
          >
            <Paragraph fontSize={13} fontWeight="700" color="$slate700">
              Appeler
            </Paragraph>
          </Button>
        ) : null}
        {isPending ? (
          <Button size="$3" backgroundColor={brand.success} color="white" borderRadius={10} disabled={busy} icon={<Check size={15} color="white" />} onPress={onConfirm}>
            {t('viewings.confirm')}
          </Button>
        ) : null}
        {(isPending || isConfirmed) ? (
          <Button size="$3" chromeless borderWidth={1} borderColor="$danger" borderRadius={10} disabled={busy} icon={<UserX size={15} color={brand.danger} />} onPress={onNoShow}>
            <Paragraph fontSize={13} fontWeight="700" color="$danger">
              {t('viewings.noShow')}
            </Paragraph>
          </Button>
        ) : null}
      </XStack>
    </YStack>
  );
}
