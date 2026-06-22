import {
  BarChart3,
  Bell,
  CalendarClock,
  ChevronRight,
  Coins,
  Eye,
  Home,
  MessageCircle,
  Plus,
  Rocket,
  Sparkles,
  TrendingUp,
} from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useCallback } from 'react';
import { Pressable, RefreshControl, ScrollView } from 'react-native';
import { Button, H1, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { FadeIn } from '@/components/FadeIn';
import { OwnerAdCard } from '@/components/OwnerAdCard';
import { StatCard } from '@/components/StatCard';
import { StatusBadge } from '@/components/StatusBadge';
import { useCreditsBalance } from '@/hooks/useCredits';
import { useMe } from '@/hooks/useMe';
import { useOwnerStats } from '@/hooks/useOwnerStats';
import { useUnreadNotificationCount } from '@/hooks/useNotifications';
import { brand } from '@/theme/tokens';
import { formatFcfa } from '@/utils/format';
import { t } from '@/i18n';

function useGreeting(): string {
  const hour = new Date().getHours();
  if (hour < 6) return 'Bonne nuit';
  if (hour < 12) return 'Bonjour';
  if (hour < 18) return 'Bon après-midi';
  return 'Bonsoir';
}

export default function Dashboard() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const me = useMe(isAuthenticated);
  const { data: stats, isLoading, isRefetching, refetch } = useOwnerStats(isAuthenticated);
  const credits = useCreditsBalance(isAuthenticated);
  const unread = useUnreadNotificationCount(isAuthenticated);

  const onRefresh = useCallback(() => {
    refetch();
    me.refetch();
    credits.refetch();
  }, [refetch, me, credits]);

  const recentAds = stats?.recent_ads ?? [];
  const breakdown = stats?.status_breakdown ?? {};
  const greeting = useGreeting();

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
        {/* Header avec bell notifs */}
        <YStack paddingHorizontal={16} gap={4} marginBottom={14}>
          <XStack alignItems="center" gap={10}>
            <YStack flex={1}>
              <Paragraph fontSize={14} color="$slate500" fontWeight="600">
                {greeting}
              </Paragraph>
              <H1 fontSize={26} fontWeight="900" numberOfLines={1}>
                {me.data?.firstname ?? 'Bailleur'}
              </H1>
            </YStack>
            <Pressable
              onPress={() => router.push('/notifications' as never)}
              hitSlop={8}
              accessibilityLabel="Notifications"
            >
              <YStack>
                <YStack
                  width={42}
                  height={42}
                  borderRadius={21}
                  backgroundColor={brand.primaryAlpha10}
                  alignItems="center"
                  justifyContent="center"
                >
                  <Bell size={18} color={brand.primary} />
                </YStack>
                {(unread.data ?? 0) > 0 ? (
                  <YStack
                    position="absolute"
                    top={-2}
                    right={-2}
                    minWidth={18}
                    height={18}
                    borderRadius={9}
                    backgroundColor={brand.danger}
                    alignItems="center"
                    justifyContent="center"
                    paddingHorizontal={4}
                    borderWidth={2}
                    borderColor="$background"
                  >
                    <Paragraph fontSize={10} fontWeight="900" color="white">
                      {(unread.data ?? 0) > 9 ? '9+' : unread.data}
                    </Paragraph>
                  </YStack>
                ) : null}
              </YStack>
            </Pressable>
          </XStack>
        </YStack>

        {/* Hero bandeau crédits */}
        <FadeIn>
          <Pressable onPress={() => router.push('/credits' as never)}>
            <XStack
              marginHorizontal={16}
              padding={14}
              borderRadius={16}
              backgroundColor={brand.primary}
              alignItems="center"
              gap={10}
              marginBottom={16}
            >
              <YStack
                width={42}
                height={42}
                borderRadius={21}
                backgroundColor="rgba(255,255,255,0.18)"
                alignItems="center"
                justifyContent="center"
              >
                <Coins size={20} color="white" />
              </YStack>
              <YStack flex={1} gap={2}>
                <Paragraph fontSize={11} fontWeight="800" color="rgba(255,255,255,0.85)" letterSpacing={0.5}>
                  SOLDE CRÉDITS
                </Paragraph>
                <Paragraph fontSize={20} fontWeight="900" color="white">
                  {credits.isLoading ? '…' : credits.data ?? 0}
                </Paragraph>
              </YStack>
              <Paragraph fontSize={12} fontWeight="800" color="white">
                Recharger
              </Paragraph>
              <ChevronRight size={18} color="white" />
            </XStack>
          </Pressable>
        </FadeIn>

        {/* Bouton primaire création */}
        <YStack paddingHorizontal={16} marginBottom={16}>
          <Button
            size="$5"
            backgroundColor="$brand"
            color="white"
            fontWeight="900"
            borderRadius={14}
            icon={<Plus size={18} color="white" />}
            onPress={() => router.push('/ads/new' as never)}
          >
            {t('dashboard.newAd')}
          </Button>
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

        {/* Quick links — 4 actions clés */}
        <YStack paddingHorizontal={16} marginTop={22} gap={10}>
          <Paragraph fontSize={16} fontWeight="800" color="$slate900">
            {t('dashboard.quickActions')}
          </Paragraph>
          <XStack gap={10}>
            <QuickLink
              icon={<Eye size={18} color={brand.primary} />}
              label={t('account.analytics')}
              onPress={() => router.push('/analytics' as never)}
            />
            <QuickLink
              icon={<MessageCircle size={18} color={brand.primary} />}
              label="Messages"
              onPress={() => router.push('/messages' as never)}
            />
          </XStack>
          <XStack gap={10}>
            <QuickLink
              icon={<Rocket size={18} color={brand.accent} />}
              label={t('account.subscription')}
              onPress={() => router.push('/subscriptions' as never)}
            />
            <QuickLink
              icon={<Sparkles size={18} color={brand.accent} />}
              label="Services premium"
              onPress={() => router.push('/pro-services' as never)}
            />
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
