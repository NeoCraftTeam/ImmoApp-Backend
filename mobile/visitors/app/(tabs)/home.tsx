import { Bell, CheckCircle2, RefreshCw, Sparkles } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { Link } from 'expo-router';
import { useCallback, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
} from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { AdCard } from '@/components/AdCard';
import { AdCardSkeleton } from '@/components/AdCardSkeleton';
import { FadeIn } from '@/components/FadeIn';
import { useAdFeed } from '@/hooks/useAdFeed';
import { useAdTypes } from '@/hooks/useCitiesAndTypes';
import { useGreeting } from '@/hooks/useGreeting';
import { useMe } from '@/hooks/useMe';
import { useRecommendations } from '@/hooks/useRecommendations';
import { useSession } from '@/auth/SessionProvider';
import { useUnreadNotificationCount } from '@/hooks/useNotifications';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';
import type { Ad } from '@/types/ad';

const TOP_PRIORITY_COUNT = 4;
const SKELETON_COUNT = 6;

/**
 * Home feed — port mobile du `(dashboard)/home` web :
 *   1. Salutation temporelle + "Bon retour parmi nous" si > 24 h
 *   2. Prénom utilisateur (rien si anonyme)
 *   3. Cloche notifications avec badge non-lu
 *   4. Carrousel horizontal de chips type de bien
 *   5. Section "Recommandées pour vous" (carrousel horizontal, /recommendations)
 *   6. Feed principal cursor-paginé en grille 2 colonnes
 *
 * La pagination passe par `useAdFeed` (TanStack `useInfiniteQuery`) ;
 * `onEndReached` déclenche `fetchNextPage()` à 0.6 viewport de la fin.
 * Throttle natif via `pendingNextPage.current` pour éviter les fires
 * multiples sur un même cursor lorsque le user accélère le scroll.
 */
export default function Home() {
  const insets = useSafeAreaInsets();
  const greeting = useGreeting();
  const { isAuthenticated } = useSession();
  const me = useMe(isAuthenticated);
  const unread = useUnreadNotificationCount();
  const adTypes = useAdTypes();

  const [selectedType, setSelectedType] = useState<string | null>(null);

  const {
    data: ads,
    isLoading,
    isError,
    error,
    refetch,
    isRefetching,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useAdFeed(15, selectedType);

  const recommendations = useRecommendations(8);

  const pendingNextPage = useRef(false);
  const handleEndReached = useCallback(() => {
    if (!hasNextPage || isFetchingNextPage || pendingNextPage.current) return;
    pendingNextPage.current = true;
    fetchNextPage().finally(() => {
      pendingNextPage.current = false;
    });
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  const firstName = me.data?.firstname?.trim();
  const showRecommendations =
    !selectedType && (recommendations.data?.length ?? 0) > 0;

  const categoryChips = useMemo(
    () => [
      { id: null as string | null, name: 'Tous' },
      ...((adTypes.data ?? []).map((adType) => ({
        id: adType.name,
        name: adType.name,
      })) as { id: string | null; name: string }[]),
    ],
    [adTypes.data],
  );

  // ── Error state ──────────────────────────────────────────────────
  if (isError && !ads) {
    return (
      <YStack
        flex={1}
        backgroundColor="$background"
        paddingTop={insets.top + 16}
        paddingHorizontal="$5"
      >
        <H2>{t('home.title')}</H2>
        <YStack flex={1} alignItems="center" justifyContent="center" gap={12}>
          <Paragraph size="$4" color="$slate700" textAlign="center">
            {extractApiErrorMessage(error)}
          </Paragraph>
          <Pressable onPress={() => refetch()} hitSlop={6}>
            <XStack
              alignItems="center"
              gap={6}
              paddingHorizontal={14}
              paddingVertical={10}
              borderRadius={999}
              backgroundColor="$slate900"
            >
              <RefreshCw size={14} color="white" />
              <Paragraph fontSize={13} fontWeight="700" color="white">
                Réessayer
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      </YStack>
    );
  }

  // ── Header (rendered above feed) ─────────────────────────────────
  const ListHeader = (
    <YStack gap={14} marginBottom={12}>
      {/* Greeting row */}
      <XStack alignItems="flex-end" gap={12} marginTop={2}>
        <YStack flex={1} gap={2}>
          <Paragraph fontSize={13} color="$slate500">
            {greeting}{firstName ? ',' : ''}
          </Paragraph>
          {firstName && (
            <H2 fontSize={24} fontWeight="800" lineHeight={28}>
              {firstName}
            </H2>
          )}
        </YStack>
        <Link href="/notifications" asChild>
          <Pressable hitSlop={6} accessibilityLabel="Notifications">
            <YStack
              width={42}
              height={42}
              borderRadius={21}
              backgroundColor="$slate100"
              alignItems="center"
              justifyContent="center"
            >
              <Bell size={20} color={brand.slate700} />
              {(unread.data ?? 0) > 0 && (
                <YStack
                  position="absolute"
                  top={6}
                  right={6}
                  minWidth={16}
                  height={16}
                  paddingHorizontal={4}
                  borderRadius={8}
                  backgroundColor={brand.primary}
                  alignItems="center"
                  justifyContent="center"
                >
                  <Paragraph fontSize={9} fontWeight="800" color="white">
                    {(unread.data ?? 0) > 99 ? '99+' : unread.data}
                  </Paragraph>
                </YStack>
              )}
            </YStack>
          </Pressable>
        </Link>
      </XStack>

      <Paragraph fontSize={14} color="$slate500" lineHeight={20}>
        Découvrez les meilleures annonces immobilières du moment.
      </Paragraph>

      {/* Category chips */}
      <FlatList
        data={categoryChips}
        keyExtractor={(item) => String(item.id ?? 'all')}
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ gap: 8, paddingVertical: 2 }}
        renderItem={({ item }) => {
          const active = (selectedType ?? null) === item.id;
          return (
            <Pressable onPress={() => setSelectedType(item.id ?? null)} hitSlop={4}>
              <XStack
                paddingHorizontal={14}
                paddingVertical={8}
                borderRadius={999}
                backgroundColor={active ? brand.slate900 : '$slate100'}
              >
                <Paragraph fontSize={13} fontWeight="700" color={active ? 'white' : '$slate700'}>
                  {item.name}
                </Paragraph>
              </XStack>
            </Pressable>
          );
        }}
      />

      {/* Recommendations carousel */}
      {showRecommendations && (
        <YStack gap={10} marginTop={4}>
          <XStack alignItems="center" gap={6}>
            <Sparkles size={16} color={brand.primary} />
            <Paragraph fontSize={15} fontWeight="700" color="$slate900">
              Recommandées pour vous
            </Paragraph>
          </XStack>
          <FlatList
            data={recommendations.data ?? []}
            keyExtractor={(item) => `reco-${item.id}`}
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ gap: 12, paddingRight: 8 }}
            renderItem={({ item, index }) => (
              <RecommendationCard ad={item} priority={index < 2} />
            )}
          />
        </YStack>
      )}

      {/* Section header */}
      <Paragraph fontSize={15} fontWeight="700" color="$slate900" marginTop={6}>
        {selectedType ? `${selectedType} · à la une` : t('home.title')}
      </Paragraph>
    </YStack>
  );

  // ── Loading state — skeleton grid (matches AdCard geometry) ───────
  if (isLoading && !ads) {
    return (
      <FlatList
        data={Array.from({ length: SKELETON_COUNT }, (_, i) => i)}
        keyExtractor={(i) => `skel-${i}`}
        numColumns={2}
        columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
        contentContainerStyle={{
          paddingTop: insets.top + 8,
          paddingBottom: insets.bottom + 16,
          paddingHorizontal: 12,
        }}
        ListHeaderComponent={ListHeader}
        renderItem={() => (
          <YStack flex={1}>
            <AdCardSkeleton />
          </YStack>
        )}
        scrollEnabled={false}
      />
    );
  }

  return (
    <FlatList
      data={ads ?? []}
      keyExtractor={(item) => item.id}
      numColumns={2}
      columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
      contentContainerStyle={{
        paddingTop: insets.top + 8,
        paddingBottom: insets.bottom + 16,
        paddingHorizontal: 12,
      }}
      ListHeaderComponent={ListHeader}
      ListEmptyComponent={
        <YStack paddingVertical={32} alignItems="center" gap={6}>
          <Paragraph fontSize={15} fontWeight="700" color="$slate900">
            {selectedType ? `Aucune annonce ${selectedType}` : t('home.empty')}
          </Paragraph>
          {selectedType && (
            <Pressable onPress={() => setSelectedType(null)} hitSlop={4}>
              <Paragraph fontSize={13} color={brand.primary} fontWeight="700" textDecorationLine="underline">
                Voir toutes les annonces
              </Paragraph>
            </Pressable>
          )}
        </YStack>
      }
      renderItem={({ item, index }) => (
        <YStack flex={1}>
          <FadeIn delay={Math.min(index, 6) * 40}>
            <AdCard ad={item} priority={index < TOP_PRIORITY_COUNT} />
          </FadeIn>
        </YStack>
      )}
      refreshControl={
        <RefreshControl
          refreshing={isRefetching}
          onRefresh={refetch}
          tintColor={brand.primary}
        />
      }
      onEndReached={handleEndReached}
      onEndReachedThreshold={0.6}
      ListFooterComponent={
        <FeedFooter
          isFetchingNextPage={isFetchingNextPage}
          hasNextPage={hasNextPage}
          itemCount={ads?.length ?? 0}
          onLoadMore={handleEndReached}
        />
      }
      removeClippedSubviews
      initialNumToRender={6}
      maxToRenderPerBatch={6}
      windowSize={11}
    />
  );
}

/**
 * Footer du feed : spinner pendant la prochaine page, message
 * "Vous avez tout vu" en fin de liste, ou bouton fallback "Charger
 * plus" si `onEndReached` n'a pas déclenché (rare mais arrive sur
 * écrans très hauts où le seuil ne tombe pas pendant un scroll lent).
 */
function FeedFooter({
  isFetchingNextPage,
  hasNextPage,
  itemCount,
  onLoadMore,
}: {
  isFetchingNextPage: boolean;
  hasNextPage: boolean | undefined;
  itemCount: number;
  onLoadMore: () => void;
}) {
  if (itemCount === 0) return null;

  if (isFetchingNextPage) {
    return (
      <YStack paddingVertical={20} alignItems="center" gap={8}>
        <ActivityIndicator color={brand.primary} />
        <Paragraph fontSize={12} color="$slate500">
          Chargement de nouvelles annonces…
        </Paragraph>
      </YStack>
    );
  }

  if (hasNextPage) {
    return (
      <YStack paddingVertical={16} alignItems="center">
        <Pressable onPress={onLoadMore} hitSlop={6}>
          <XStack
            alignItems="center"
            gap={6}
            paddingHorizontal={16}
            paddingVertical={10}
            borderRadius={999}
            borderWidth={1}
            borderColor="$slate300"
          >
            <RefreshCw size={13} color={brand.slate700} />
            <Paragraph fontSize={13} fontWeight="700" color="$slate700">
              Charger plus
            </Paragraph>
          </XStack>
        </Pressable>
      </YStack>
    );
  }

  return (
    <YStack paddingVertical={24} alignItems="center" gap={4}>
      <CheckCircle2 size={20} color={brand.success} />
      <Paragraph fontSize={13} fontWeight="700" color="$slate700">
        Vous avez tout vu
      </Paragraph>
      <Paragraph fontSize={11} color="$slate500">
        {itemCount} annonce{itemCount > 1 ? 's' : ''} affichée{itemCount > 1 ? 's' : ''}
      </Paragraph>
    </YStack>
  );
}

/**
 * Carte compacte horizontale pour le carrousel "Recommandées".
 * Reprend le design language d'AdCard mais à une échelle plus petite
 * pour qu'environ deux cartes tiennent à l'écran.
 */
function RecommendationCard({ ad, priority }: { ad: Ad; priority: boolean }) {
  const cover = ad.images?.find((i) => i.is_primary) ?? ad.images?.[0];
  const coverUri = cover?.thumb ?? cover?.url;
  const locationLabel = [ad.quarter?.name, ad.quarter?.city_name]
    .filter(Boolean)
    .join(', ');
  const periodLabel = ad.price_period === 'jour' ? '/jour' : '/mois';
  const isRent = ad.transaction_type === 'location';

  return (
    <Link
      href={{ pathname: '/ads/[slug]', params: { slug: ad.slug ?? ad.id } }}
      asChild
    >
      <Pressable accessibilityRole="link" accessibilityLabel={ad.title}>
        <YStack width={220} gap={8}>
          <YStack
            width={220}
            height={140}
            borderRadius={14}
            overflow="hidden"
            backgroundColor="$slate100"
          >
            {coverUri ? (
              <Image
                source={{ uri: coverUri }}
                style={{ width: '100%', height: '100%' }}
                contentFit="cover"
                transition={200}
                priority={priority ? 'high' : 'normal'}
                accessibilityLabel={ad.title}
              />
            ) : null}
          </YStack>
          <YStack gap={2}>
            <Paragraph fontSize={13.5} fontWeight="700" color="$slate900" numberOfLines={1}>
              {ad.title}
            </Paragraph>
            {locationLabel.length > 0 && (
              <Paragraph fontSize={11.5} color="$slate500" numberOfLines={1}>
                {locationLabel}
              </Paragraph>
            )}
            <XStack alignItems="baseline" gap={3} marginTop={2}>
              <Paragraph fontSize={13} fontWeight="800" color="$slate900" numberOfLines={1}>
                {ad.price != null
                  ? `${ad.price.toLocaleString('fr-FR')} FCFA`
                  : '—'}
              </Paragraph>
              {ad.price != null && isRent && (
                <Paragraph fontSize={11} color="$slate500">
                  {periodLabel}
                </Paragraph>
              )}
            </XStack>
          </YStack>
        </YStack>
      </Pressable>
    </Link>
  );
}
