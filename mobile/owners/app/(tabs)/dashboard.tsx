import {
  BarChart3,
  CalendarClock,
  Eye,
  Home,
  Plus,
  Rocket,
  TrendingUp,
} from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useCallback } from 'react';
import { Pressable, RefreshControl, ScrollView } from 'react-native';
import { Button, H1, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { OwnerAdCard } from '@/components/OwnerAdCard';
import { StatCard } from '@/components/StatCard';
import { StatusBadge } from '@/components/StatusBadge';
import { useMe } from '@/hooks/useMe';
import { useOwnerStats } from '@/hooks/useOwnerStats';
import { brand } from '@/theme/tokens';
import { formatCompact, formatFcfa } from '@/utils/format';
import { t } from '@/i18n';

export default function Dashboard() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const me = useMe(isAuthenticated);
  const { data: stats, isLoading, isRefetching, refetch } = useOwnerStats(isAuthenticated);

  const onRefresh = useCallback(() => {
    refetch();
    me.refetch();
  }, [refetch, me]);

  const recentAds = stats?.recent_ads ?? [];
  const breakdown = stats?.status_breakdown ?? {};

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + 12,
          paddingBottom: 28,
        }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={onRefresh} tintColor={brand.primary} />
        }
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <YStack paddingHorizontal={16} gap={4} marginBottom={18}>
          <Paragraph fontSize={14} color="$slate500" fontWeight="600">
            {t('dashboard.greeting')}
          </Paragraph>
          <XStack alignItems="center" justifyContent="space-between">
            <H1 fontSize={26} fontWeight="900" flex={1} numberOfLines={1}>
              {me.data?.firstname ?? 'Bailleur'}
            </H1>
            <Button
              size="$3"
              backgroundColor="$brand"
              color="white"
              fontWeight="800"
              borderRadius={12}
              icon={<Plus size={16} color="white" />}
              onPress={() => router.push('/ads/new' as never)}
            >
              {t('dashboard.newAd')}
            </Button>
          </XStack>
        </YStack>

        {/* KPI grid */}
        <YStack paddingHorizontal={16} gap={10}>
          <XStack gap={10}>
            <StatCard
              label={t('dashboard.stats.activeAds')}
              value={isLoading ? '—' : stats?.active_ads_count ?? 0}
              icon={<Home size={16} color={brand.primary} />}
              accent={brand.primary}
              hint={`${stats?.total_ads_count ?? 0} au total`}
            />
            <StatCard
              label={t('dashboard.stats.occupancy')}
              value={isLoading ? '—' : `${Math.round(stats?.occupancy_rate ?? 0)}%`}
              icon={<TrendingUp size={16} color={brand.success} />}
              accent={brand.success}
            />
          </XStack>
          <XStack gap={10}>
            <StatCard
              label={t('dashboard.stats.boosts')}
              value={isLoading ? '—' : stats?.active_boosts_count ?? 0}
              icon={<Rocket size={16} color={brand.accent} />}
              accent={brand.accent}
            />
            <StatCard
              label={t('dashboard.stats.pendingViewings')}
              value={isLoading ? '—' : stats?.recent_viewings_count ?? 0}
              icon={<CalendarClock size={16} color={brand.secondary} />}
              accent={brand.secondary}
            />
          </XStack>
          <StatCard
            label={t('dashboard.stats.revenueMonth')}
            value={isLoading ? '—' : formatFcfa(stats?.this_month_revenue)}
            icon={<BarChart3 size={16} color={brand.primary} />}
            accent={brand.primary}
            hint={`${formatFcfa(stats?.total_revenue)} cumulés`}
          />
        </YStack>

        {/* Status breakdown */}
        {Object.keys(breakdown).length > 0 ? (
          <YStack paddingHorizontal={16} marginTop={22} gap={10}>
            <Paragraph fontSize={16} fontWeight="800" color="$slate900">
              {t('dashboard.statusBreakdown')}
            </Paragraph>
            <XStack flexWrap="wrap" gap={8}>
              {Object.entries(breakdown).map(([status, count]) =>
                count ? (
                  <XStack key={status} alignItems="center" gap={6}>
                    <StatusBadge status={status} size="sm" />
                    <Paragraph fontSize={13} fontWeight="800" color="$slate700">
                      {count}
                    </Paragraph>
                  </XStack>
                ) : null,
              )}
            </XStack>
          </YStack>
        ) : null}

        {/* Quick links */}
        <YStack paddingHorizontal={16} marginTop={22} gap={10}>
          <Paragraph fontSize={16} fontWeight="800" color="$slate900">
            {t('dashboard.quickActions')}
          </Paragraph>
          <XStack gap={10}>
            <QuickLink icon={<Eye size={18} color={brand.primary} />} label={t('account.analytics')} onPress={() => router.push('/analytics' as never)} />
            <QuickLink icon={<Rocket size={18} color={brand.accent} />} label={t('account.subscription')} onPress={() => router.push('/subscriptions' as never)} />
          </XStack>
        </YStack>

        {/* Recent ads */}
        <YStack paddingHorizontal={16} marginTop={22} gap={12}>
          <XStack alignItems="center" justifyContent="space-between">
            <Paragraph fontSize={16} fontWeight="800" color="$slate900">
              {t('dashboard.recentAds')}
            </Paragraph>
            <Pressable onPress={() => router.push('/(tabs)/ads' as never)} hitSlop={8}>
              <Paragraph fontSize={13} fontWeight="700" color="$brand">
                {t('common.seeAll')}
              </Paragraph>
            </Pressable>
          </XStack>

          {recentAds.length === 0 && !isLoading ? (
            <EmptyState
              icon={<Home size={28} color={brand.primary} />}
              title={t('ads.empty')}
              hint={t('ads.emptyHint')}
              ctaLabel={t('ads.create')}
              onPressCta={() => router.push('/ads/new' as never)}
            />
          ) : (
            <YStack gap={10}>
              {recentAds.map((ad) => (
                <OwnerAdCard key={ad.id} ad={ad} />
              ))}
            </YStack>
          )}
        </YStack>
      </ScrollView>
    </YStack>
  );
}

function QuickLink({
  icon,
  label,
  onPress,
}: {
  icon: React.ReactNode;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable style={{ flex: 1 }} onPress={onPress}>
      <XStack
        flex={1}
        alignItems="center"
        gap={10}
        padding={14}
        borderRadius={14}
        borderWidth={1}
        borderColor="$slate300"
        backgroundColor="$background"
      >
        {icon}
        <Paragraph fontSize={13.5} fontWeight="700" color="$slate900" flex={1} numberOfLines={1}>
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}
