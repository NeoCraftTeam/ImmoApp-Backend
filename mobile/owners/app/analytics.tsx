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
