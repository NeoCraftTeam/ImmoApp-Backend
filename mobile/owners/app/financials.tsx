import { ArrowDownRight, ArrowUpRight, TrendingUp, Wallet } from '@tamagui/lucide-icons';
import { RefreshControl, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { StatCard } from '@/components/StatCard';
import { useOwnerStats } from '@/hooks/useOwnerStats';
import { brand } from '@/theme/tokens';
import { formatFcfa } from '@/utils/format';

export default function FinancialsScreen() {
  const { isAuthenticated } = useSession();
  const { data, isLoading, isRefetching, refetch } = useOwnerStats(isAuthenticated);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Finances" subtitle="Vue d'ensemble revenus & occupation" />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 14 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : !data ? (
          <YStack height={320}>
            <EmptyState
              icon={<Wallet size={28} color={brand.primary} />}
              title="Aucune donnée financière"
              hint="Activez au moins une annonce pour suivre vos revenus."
            />
          </YStack>
        ) : (
          <>
            <XStack gap={10}>
              <StatCard
                label="Revenus du mois"
                value={formatFcfa(data.this_month_revenue ?? 0)}
                icon={<ArrowUpRight size={16} color={brand.success} />}
                accent={brand.success}
              />
              <StatCard
                label="Revenus total"
                value={formatFcfa(data.total_revenue ?? 0)}
                icon={<Wallet size={16} color={brand.primary} />}
                accent={brand.primary}
              />
            </XStack>
            <XStack gap={10}>
              <StatCard
                label="Taux d'occupation"
                value={`${Math.round(data.occupancy_rate ?? 0)}%`}
                icon={<TrendingUp size={16} color={brand.secondary} />}
                accent={brand.secondary}
              />
              <StatCard
                label="Annonces actives"
                value={String(data.active_ads_count ?? 0)}
                icon={<ArrowDownRight size={16} color={brand.accent} />}
                accent={brand.accent}
              />
            </XStack>

            <YStack
              marginTop={6}
              padding={14}
              borderRadius={14}
              borderWidth={1}
              borderColor="$slate300"
              gap={6}
            >
              <Paragraph fontSize={13} fontWeight="800" color="$slate900">
                Détails par annonce
              </Paragraph>
              <Paragraph fontSize={12.5} color="$slate500">
                Le détail des charges, dépenses et P&L par bien est accessible depuis le détail de chaque annonce.
              </Paragraph>
            </YStack>
          </>
        )}
      </ScrollView>
    </YStack>
  );
}
