import {
  Bell,
  ChevronRight,
  Coins,
  Eye,
  Home,
  MessageCircle,
  Plus,
  Rocket,
  Sparkles,
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
import { useCreditsBalance } from '@/hooks/useCredits';
import { useMe } from '@/hooks/useMe';
import { useMyAds } from '@/hooks/useMyAds';
import { useOwnerStats } from '@/hooks/useOwnerStats';
import { useUnreadNotificationCount } from '@/hooks/useNotifications';
import { brand, tabularNumStyle } from '@/theme/tokens';
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
  // `/my/stats` ne renvoie pas les annonces récentes : on les tire de la
  // liste paginée /my/ads (première page).
  const { data: adsPages, refetch: refetchAds } = useMyAds({}, isAuthenticated);

  const onRefresh = useCallback(() => {
    refetch();
    me.refetch();
    credits.refetch();
    refetchAds();
  }, [refetch, me, credits, refetchAds]);

  const recentAds = (adsPages?.pages[0]?.data ?? []).slice(0, 3);
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

        {/* ──────────────────────────────────────────────────────────
            HERO REVENUS — 1 chiffre dominant + textline KPIs
            (vs. ancienne grille 2×2 StatCards identique = AI-slop).
            Inspiration : Stripe dashboard, Linear metrics overview.
            ────────────────────────────────────────────────────────── */}
        <YStack paddingHorizontal={20} marginBottom={32}>
          <Paragraph
            fontSize={11}
            fontWeight="800"
            color="$slate500"
            letterSpacing={1.4}
            textTransform="uppercase"
            marginBottom={8}
          >
            Loyers encaissés (30 j)
          </Paragraph>
          <Paragraph
            fontSize={48}
            fontWeight="900"
            color="$slate900"
            letterSpacing={-1.5}
            lineHeight={52}
            style={tabularNumStyle}
          >
            {isLoading ? '—' : formatFcfa(stats?.rent_collected_xaf_30d ?? 0)}
          </Paragraph>
          {!isLoading && stats?.monthly_rent_total_xaf != null ? (
            <Paragraph
              fontSize={12.5}
              color="$slate500"
              marginTop={6}
              fontWeight="600"
            >
              {formatFcfa(stats.monthly_rent_total_xaf)} attendus / mois
            </Paragraph>
          ) : null}
        </YStack>

        {/* KPIs secondaires : textline avec separateurs verticaux —
            ZERO card, ZERO icon-container, juste de la typo. */}
        <XStack
          paddingHorizontal={20}
          marginBottom={28}
          gap={0}
          alignItems="flex-end"
        >
          <InlineMetric
            value={isLoading ? '—' : String(stats?.active_ads_count ?? 0)}
            label="annonces"
            tone="primary"
            onPress={() => router.push('/(tabs)/ads' as never)}
          />
          <YStack width={1} height={36} backgroundColor="$slate200" marginHorizontal={18} />
          <InlineMetric
            value={isLoading ? '—' : `${Math.round(stats?.occupancy_rate ?? 0)}%`}
            label="occupées"
            tone="success"
          />
          <YStack width={1} height={36} backgroundColor="$slate200" marginHorizontal={18} />
          <InlineMetric
            value={isLoading ? '—' : String(stats?.pending_viewings_count ?? 0)}
            label="visites"
            tone="secondary"
            onPress={() => router.push('/(tabs)/viewings' as never)}
          />
        </XStack>

        {/* Boosts en bandeau accent doré (si actifs uniquement) —
            crée un point d'asymétrie + breaks le rythme texte/texte. */}
        {!isLoading && (stats?.active_boosts_count ?? 0) > 0 ? (
          <Pressable onPress={() => router.push('/pro-services' as never)}>
            <XStack
              marginHorizontal={16}
              padding={14}
              marginBottom={28}
              borderRadius={14}
              backgroundColor={brand.accentAlpha10}
              alignItems="center"
              gap={12}
            >
              <Rocket size={18} color={brand.accentDark} />
              <Paragraph fontSize={13} color={brand.accentDark} flex={1} fontWeight="700">
                {stats?.active_boosts_count} boost{(stats?.active_boosts_count ?? 0) > 1 ? 's' : ''} actif{(stats?.active_boosts_count ?? 0) > 1 ? 's' : ''} en ce moment
              </Paragraph>
              <ChevronRight size={16} color={brand.accentDark} />
            </XStack>
          </Pressable>
        ) : null}


        {/* Quick links — INLINE action bar, plus de grille 2×2 :
            compact, sans border, hierarchie text-link visible. */}
        <YStack paddingHorizontal={20} marginBottom={28} gap={14}>
          <SectionLabel>Aller à</SectionLabel>
          <XStack flexWrap="wrap" gap={8} rowGap={8}>
            <InlineAction
              icon={<Eye size={14} color={brand.primary} />}
              label={t('account.analytics')}
              onPress={() => router.push('/analytics' as never)}
            />
            <InlineAction
              icon={<MessageCircle size={14} color={brand.primary} />}
              label="Messages"
              onPress={() => router.push('/messages' as never)}
            />
            <InlineAction
              icon={<Rocket size={14} color={brand.accentDark} />}
              label={t('account.subscription')}
              onPress={() => router.push('/subscriptions' as never)}
            />
            <InlineAction
              icon={<Sparkles size={14} color={brand.accentDark} />}
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
              title="Aucune annonce, mais le marché vous attend."
              hint="Créez votre première annonce pour commencer à recevoir des demandes de visite et des messages de prospects."
              tip="Conseil : une photo nette en lumière naturelle augmente vos vues de 50 %. Soignez aussi le titre — pas plus de 8 mots."
              ctaLabel="Créer ma première annonce"
              onPressCta={() => router.push('/ads/new' as never)}
              secondaryLabel="Voir les services premium"
              onPressSecondary={() => router.push('/pro-services' as never)}
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

/**
 * KPI compact inline — texte seul, ZERO card, ZERO icon container.
 * Le chiffre est l'element dominant (28px tabular), le label en sub.
 * Optionnellement cliquable. Inspiration Stripe metrics overview.
 */
function InlineMetric({
  value,
  label,
  subLabel,
  tone,
  onPress,
}: {
  value: string;
  label: string;
  subLabel?: string;
  tone: 'primary' | 'success' | 'secondary' | 'accent';
  onPress?: () => void;
}) {
  const colorMap = {
    primary: brand.primary,
    success: brand.success,
    secondary: brand.secondary,
    accent: brand.accentDark,
  } as const;
  const content = (
    <YStack gap={3} flex={1}>
      <Paragraph
        fontSize={26}
        fontWeight="900"
        color={colorMap[tone]}
        letterSpacing={-0.6}
        style={tabularNumStyle}
      >
        {value}
        {subLabel ? (
          <Paragraph fontSize={13} color="$slate400" fontWeight="600">
            {' '}{subLabel}
          </Paragraph>
        ) : null}
      </Paragraph>
      <Paragraph fontSize={11.5} color="$slate500" fontWeight="600" letterSpacing={0.2}>
        {label}
      </Paragraph>
    </YStack>
  );
  if (onPress) {
    return <Pressable onPress={onPress} style={{ flex: 1 }}>{content}</Pressable>;
  }
  return content;
}

/**
 * Action inline — pill compact sans border, juste icone + label avec
 * separateur fin a droite (sauf le dernier). C'est l'inverse d'un
 * "bouton CTA" : ces actions sont equivalentes et discreetes.
 */
function InlineAction({
  icon,
  label,
  onPress,
}: {
  icon: React.ReactNode;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress}>
      <XStack
        alignItems="center"
        gap={6}
        paddingHorizontal={12}
        paddingVertical={9}
        borderRadius={999}
        backgroundColor="$slate100"
      >
        {icon}
        <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}

/**
 * Label de section minimaliste : caps + tracking large + sans line.
 * Inspiration : Notion / Linear sidebar headings. Pas d'icone, pas
 * de border, juste une typo qui dit "voici un groupe".
 */
function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <Paragraph
      fontSize={10.5}
      fontWeight="800"
      color="$slate500"
      letterSpacing={1.5}
      textTransform="uppercase"
    >
      {children}
    </Paragraph>
  );
}
