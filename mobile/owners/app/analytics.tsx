import { BarChart3, Eye, Heart, Phone, Sparkles, TrendingUp } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { RefreshControl, ScrollView } from 'react-native';
import { Button, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { OwnerAdCard } from '@/components/OwnerAdCard';
import { ScreenHeader } from '@/components/ScreenHeader';
import { StatCard } from '@/components/StatCard';
import { useAnalytics } from '@/hooks/useAnalytics';
import { brand } from '@/theme/tokens';
import { formatCompact } from '@/utils/format';
import { t } from '@/i18n';
import type { AnalyticsTrends } from '@/types/owner';

type Period = '7d' | '30d' | '90d';

const PERIODS: { value: Period; key: string }[] = [
  { value: '7d', key: 'analytics.period7' },
  { value: '30d', key: 'analytics.period30' },
  { value: '90d', key: 'analytics.period90' },
];

export default function AnalyticsScreen() {
  const { isAuthenticated } = useSession();
  const [period, setPeriod] = useState<Period>('30d');
  const { data, isLoading, isRefetching, refetch } = useAnalytics(period, isAuthenticated);

  const totals = data?.totals;
  const topAds = data?.top_ads ?? [];

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('analytics.title')} />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 16 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
        showsVerticalScrollIndicator={false}
      >
        {/* Period selector */}
        <XStack backgroundColor="$slate100" borderRadius={999} padding={4} gap={4}>
          {PERIODS.map((p) => {
            const isActive = period === p.value;
            return (
              <Button
                key={p.value}
                flex={1}
                size="$3"
                chromeless
                borderRadius={999}
                backgroundColor={isActive ? '$brand' : 'transparent'}
                onPress={() => setPeriod(p.value)}
                pressStyle={{ opacity: 0.85 }}
              >
                <Paragraph fontSize={13} fontWeight="800" color={isActive ? 'white' : '$slate700'}>
                  {t(p.key)}
                </Paragraph>
              </Button>
            );
          })}
        </XStack>

        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : (
          <>
            {/* Totals grid */}
            <YStack gap={10}>
              <XStack gap={10}>
                <StatCard
                  label={t('analytics.impressions')}
                  value={formatCompact(totals?.impressions)}
                  icon={<BarChart3 size={16} color={brand.secondary} />}
                  accent={brand.secondary}
                />
                <StatCard
                  label={t('analytics.views')}
                  value={formatCompact(totals?.views)}
                  icon={<Eye size={16} color={brand.primary} />}
                  accent={brand.primary}
                />
              </XStack>
              <XStack gap={10}>
                <StatCard
                  label={t('analytics.favorites')}
                  value={formatCompact(totals?.favorites)}
                  icon={<Heart size={16} color={brand.danger} />}
                  accent={brand.danger}
                />
                <StatCard
                  label={t('analytics.contacts')}
                  value={formatCompact(totals?.contact_clicks)}
                  icon={<Phone size={16} color={brand.accent} />}
                  accent={brand.accent}
                />
              </XStack>
              <XStack gap={10}>
                <StatCard
                  label={t('analytics.conversionRate')}
                  value={`${Math.round(totals?.conversion_rate ?? 0)}%`}
                  icon={<TrendingUp size={16} color={brand.success} />}
                  accent={brand.success}
                />
                <StatCard
                  label={t('analytics.engagementRate')}
                  value={`${Math.round(totals?.engagement_rate ?? 0)}%`}
                  icon={<Sparkles size={16} color={brand.primary} />}
                  accent={brand.primary}
                />
              </XStack>
            </YStack>

            {/* Trends chart — vues + favoris par jour */}
            <TrendChart trends={data?.trends} />

            {/* Top ads */}
            <YStack gap={12} marginTop={6}>
              <Paragraph fontSize={16} fontWeight="800" color="$slate900">
                {t('analytics.topAds')}
              </Paragraph>
              {topAds.length === 0 ? (
                <YStack height={300}>
                  <EmptyState
                    icon={<BarChart3 size={28} color={brand.primary} />}
                    title={t('analytics.topAds')}
                    hint="Aucune donnée pour cette période."
                  />
                </YStack>
              ) : (
                <YStack gap={10}>
                  {topAds.map((ad) => (
                    <OwnerAdCard key={ad.id} ad={ad} />
                  ))}
                </YStack>
              )}
            </YStack>
          </>
        )}
      </ScrollView>
    </YStack>
  );
}

/**
 * Mini-graphique en barres (sans dépendance) : superpose les séries
 * quotidiennes « vues » et « favoris » de `trends`. Les hauteurs sont
 * mises à l'échelle du max global ; on limite l'affichage aux ~14
 * derniers jours pour rester lisible sur mobile.
 */
function TrendChart({ trends }: { trends?: AnalyticsTrends }) {
  const views = trends?.view ?? [];
  const favorites = trends?.favorite ?? [];

  if (views.length === 0 && favorites.length === 0) {
    return null;
  }

  const byDate = new Map<string, { views: number; favorites: number }>();
  for (const p of views) {
    byDate.set(p.date, { views: p.count, favorites: byDate.get(p.date)?.favorites ?? 0 });
  }
  for (const p of favorites) {
    const prev = byDate.get(p.date);
    byDate.set(p.date, { views: prev?.views ?? 0, favorites: p.count });
  }

  const points = Array.from(byDate.entries())
    .map(([date, v]) => ({ date, ...v }))
    .sort((a, b) => a.date.localeCompare(b.date))
    .slice(-14);

  const max = Math.max(1, ...points.map((p) => Math.max(p.views, p.favorites)));
  const totalViews = points.reduce((s, p) => s + p.views, 0);
  const totalFav = points.reduce((s, p) => s + p.favorites, 0);

  const dayLabel = (iso: string): string => {
    const parts = iso.split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}` : iso;
  };

  return (
    <YStack
      gap={12}
      padding={16}
      borderRadius={16}
      borderWidth={1}
      borderColor="$slate200"
      backgroundColor="$background"
    >
      <XStack alignItems="center" justifyContent="space-between">
        <Paragraph fontSize={15} fontWeight="800" color="$slate900">
          {t('analytics.views')} & {t('analytics.favorites')}
        </Paragraph>
        <XStack gap={12}>
          <XStack alignItems="center" gap={5}>
            <YStack width={9} height={9} borderRadius={2} backgroundColor={brand.primary} />
            <Paragraph fontSize={11} color="$slate600" fontWeight="700">
              {formatCompact(totalViews)}
            </Paragraph>
          </XStack>
          <XStack alignItems="center" gap={5}>
            <YStack width={9} height={9} borderRadius={2} backgroundColor={brand.danger} />
            <Paragraph fontSize={11} color="$slate600" fontWeight="700">
              {formatCompact(totalFav)}
            </Paragraph>
          </XStack>
        </XStack>
      </XStack>

      <XStack height={120} alignItems="flex-end" gap={points.length > 10 ? 3 : 6}>
        {points.map((p) => (
          <YStack key={p.date} flex={1} alignItems="center" justifyContent="flex-end" gap={3} height="100%">
            <XStack flex={1} alignItems="flex-end" justifyContent="center" gap={2} width="100%">
              <YStack
                flex={1}
                maxWidth={10}
                height={`${Math.max(2, (p.views / max) * 100)}%`}
                backgroundColor={brand.primary}
                borderTopLeftRadius={3}
                borderTopRightRadius={3}
              />
              <YStack
                flex={1}
                maxWidth={10}
                height={`${Math.max(2, (p.favorites / max) * 100)}%`}
                backgroundColor={brand.danger}
                borderTopLeftRadius={3}
                borderTopRightRadius={3}
              />
            </XStack>
          </YStack>
        ))}
      </XStack>

      <XStack justifyContent="space-between">
        <Paragraph fontSize={10} color="$slate500">
          {dayLabel(points[0]?.date ?? '')}
        </Paragraph>
        <Paragraph fontSize={10} color="$slate500">
          {dayLabel(points[points.length - 1]?.date ?? '')}
        </Paragraph>
      </XStack>
    </YStack>
  );
}
